# design — retrofit-role-management-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit（`bdf668a`、`ce741b3`、`4b2091d`），本 change 只產出 spec 文件、不寫程式。設計重點在於「spec 內容如何忠實反映現狀的設計決策」，以下記錄已實作架構背後的關鍵決策，作為 spec Requirements 的依據。

## Goals / Non-Goals

**Goals:**
- 一份 `role-management` capability spec 完整描述角色 CRUD 與權限系統的可觀察行為
- 每個 Scenario 盡可能對應一個既有測試案例，spec 可被測試驗證
- 記錄「刻意不做」的設計與其威脅模型依據，避免後人誤判為缺漏

**Non-Goals:**
- 不改任何程式碼、不新增測試
- 不規範使用者管理（`users.role_id` 指派流程）細節，屬 user-management 範疇
- 不涵蓋登入時權限載入 session 的時機（屬 auth capability，見 `openspec/specs/auth/spec.md` 的「登入成功」Requirement）

## Decisions

1. **自建權限系統，不安裝 spatie/laravel-permission**：專案規模小、單一 guard、需求明確（`Module.Action` 格式 + 單一角色），避免不必要的套件依賴（spec/3 原始決策）。
2. **權限檢查集中在 middleware**：`#[RequiresPermission]` attribute 優先、`Module.method` 自動推導為 fallback 安全網；closure route 無 controller 一律 403。不使用 Gate/Policy。
3. **無權限導向 default_route 而非 403**：後台 UX 決策——使用者誤入無權限頁面時導向其角色預設頁；僅在 default_route 即當前權限或無法解析時 403。
4. **Session 權限快取 + 版本戳即時撤銷**：版本戳 = `max(users.updated_at, roles.updated_at)`，不需新增欄位；`syncPermissions()` 內 `$role->touch()`、改 `role_id` 時 Eloquent 自動 touch user。版本戳必須直接走 DB 查詢（避免 Eloquent 屬性快取造成永不 reload）。已知限制：未掛 `permission` middleware 的場景（如 sidebar）依賴登入時載入的 session。
5. **不採 route model binding**：保留 `{id}` + `findRoleById()` + redirect-with-flash 的 UX（找不到角色時回列表頁帶 error flash，而非 404 頁）。`destroy` 為 axios 請求，找不到角色回 422 JSON（與「仍有使用者」同碼，2026-06-12 design review 裁示維持現狀）。
6. **default_route 雙重驗證**：`exists:permissions,name` 之外，再以 `PermissionRouteResolver` 確認可解析至實際命名路由——permission 存在於 DB ≠ controller method 仍存在（spec/15 P3 修正）。
7. **default_route 權限自動補入**：spec/3 原設計為「驗證 permissions 須包含 default_route」，實作演進為 Service 層自動補入（`ensureDefaultPermission()`），對使用者更友善且不可能繞過。
8. **多步寫入包 transaction**：create+sync、update+sync、detach+delete 以 `DB::transaction()` 包覆（2026-06-12 design review 修正 B），避免半完成狀態。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → 每個 Scenario 綁定一個既有測試；行為變更時測試會先失敗，提醒同步更新 spec
- [每個 protected request 多 2 個輕量 SELECT（版本戳）] → 接受：內部後台流量低，省去 Redis 依賴
- [刪除角色的 hasUsers 檢查與刪除之間存在理論上的 race] → 接受：內部後台低併發；`users.role_id` FK 約束列為後續可做（spec/15 P2）
- [sidebar 等未掛 middleware 場景的權限顯示可能短暫 stale] → 接受：下一次進入 protected route 即修正
