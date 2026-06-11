# tasks — retrofit-role-management-spec

本 change 為文件回填：程式實作已於 commit `bdf668a`（refactor(roles): Controller 查詢改走 Repository 並移除無呼叫端方法）、`ce741b3`（fix(roles): 角色建立/更新/刪除多步寫入以 transaction 包覆）與 `4b2091d`（test(roles): 新增 PermissionFactory 並補齊 failure/edge 測試）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於 bdf668a / ce741b3 / 4b2091d）

- [x] 1.1 RoleController 三處 `Role::find()` 改經 RoleService → `RoleRepository::findById()`（bdf668a）
- [x] 1.2 移除無呼叫端的 `RoleRepository::findByNameOrFail()` 與 `PermissionRouteResolver::clearCache()`（bdf668a）
- [x] 1.3 `createRole` / `updateRole` / `deleteRole` 以 `DB::transaction()` 包覆多步寫入（ce741b3）
- [x] 1.4 新增 `PermissionFactory`、Permission model 加 `HasFactory`（4b2091d）
- [x] 1.5 補 6 個 failure/edge 測試（不存在 id 的 edit/update/destroy、Viewer 寫入操作被擋、無效 permission id、刪除後 pivot 清空）；PermissionTest 27 passed（4b2091d）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/role-management/spec.md（Requirement + Scenario，對應既有測試）
- [x] 2.4 刪除舊版 `spec/3-permission-system.md` 與 `spec/15-role-security-review.md`
- [x] 2.5 `openspec validate retrofit-role-management-spec` 通過後 archive，合併至 `openspec/specs/role-management/spec.md`

## 3. HTML 文件

- [x] 3.1 從 spec.md 產生 `docs/role-management-spec.html`（單檔自包含、零外部資源，範本 `docs/auth-spec.html`）
- [x] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [x] 3.3 全套件測試確認無回歸（222 passed）
