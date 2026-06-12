# design — retrofit-grades-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit（`934c2c1`、`9129b44`、`16890c1`、`e92d71f`），本 change 只產出 spec 文件、不寫程式。以下記錄已實作架構背後的關鍵決策，作為 spec Requirements 的依據。

## Goals / Non-Goals

**Goals:**
- 一份 `grade-management` capability spec 完整描述版本 CRU、權重系統與停用規則的可觀察行為
- 每個 Scenario 盡可能對應一個既有測試案例，spec 可被測試驗證
- 記錄「刻意不做」的設計（無刪除功能）與其依據，避免後人誤判為缺漏

**Non-Goals:**
- 不改任何程式碼、不新增測試
- 不規範 addon 與 grade 的關聯管理（`grade_ids` 同步、SyncShopAddonsForGrade job），屬 addon-management 範疇；本 spec 僅規範「停用版本不可被新關聯」的 grade 側規則
- 不涵蓋 bill 升級/續約的權重比較邏輯（屬 bill 範疇），僅規範「新 bill 不可用停用版本」

## Decisions

1. **不支援刪除，僅 status 切換**：版本被 shop / bill detail / addon 引用，刪除會破壞參照穩定性（spec/5 原始決策）。無 DELETE 路由、無 destroy 方法、權限模組無 `Grade.delete` action。
2. **停用＝「不再賣」，只擋新指派、保留既有引用**：停用版本的既有引用不被回頭驗證或清空（否則管理員只想改名稱也會被驗證擋住）；新指派一律擋下，豁免邊界各功能不同——Shop 維持原 grade 允許、Bill 無豁免、Addon 已關聯允許保留（spec/5 設計）。
3. **weight 決定等級高低，數值越高等級越高**：列表與表單顯示區依 weight 降序；唯一性三層保證——FormRequest `unique` 規則、checkWeight 即時檢查（UX）、DB unique index（防繞過）。default(0) 與 unique index 矛盾，已移除（2026-06-12 design review，commit `16890c1`）。
4. **不採 route model binding**：保留 `{id}` + `findGradeById()` + redirect-with-flash 的 UX（源自 spec/7）。`toggleStatus` 為 axios 請求，找不到回 422 JSON。錯誤文案統一「找不到該版本」（spec/7 原寫「方案」為筆誤，2026-06-12 修正）。
5. **toggleStatus 回 JSON 而非 redirect**：spec/5 原設計為 form POST + redirect flash，後續比照 spec/10 的 axios + 自訂確認 modal 模式改版，免整頁 reload。
6. **checkWeight 綁 `Grade.update` 權限**：與 edit/update 一致，僅有編輯權限者可查詢；weight<1 直接回空結果（前端已有 min:1 提示，不需查 DB）。
7. **status 以 PHP Backed Enum（`GradeStatus`）建模**：Model cast + FormRequest `Enum` rule，型別安全（spec/5 引入的專案第一個 Backed Enum）。
8. **分層比照 role-management 基準**：Controller 不直呼 Eloquent（`findGradeById` 經 Service → Repository）、checkWeight payload 組裝下沉 Service（2026-06-12 design review，commit `934c2c1`）。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → 每個 Scenario 綁定既有測試；行為變更時測試先失敗，提醒同步更新 spec
- [toggle 鈕無權限時的 badge fallback 用 `@if hasPermissionTo` 而非 `<x-permission>`] → 接受：component 無 else slot，為顯示替代內容的最小作法
- [checkWeight 與表單送出之間存在 TOCTOU race（兩人同時送同 weight）] → 接受：FormRequest unique + DB unique index 雙重兜底，後送者收到驗證錯誤或 DB constraint 例外
- [GradeSeeder 無專屬測試] → 接受：updateOrCreate 冪等模式與 PermissionSeeder 相同，已被間接驗證
