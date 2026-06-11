# role-management Specification (delta)

## ADDED Requirements

### Requirement: 角色列表

系統 SHALL 在 `GET /roles`（需 `Role.index` 權限）顯示所有角色，每筆 SHALL 含名稱、說明、預設頁面的人類可讀說明（由 permission description 對照）、權限數與使用者數（`withCount`）。

#### Scenario: 有權限者可見角色列表
- **GIVEN** 持有 `Role.index` 權限的使用者
- **WHEN** GET `/roles`
- **THEN** 系統回應 200 並渲染角色列表（含權限數與使用者數）

---

### Requirement: 新增角色

`POST /roles`（需 `Role.create` 權限）SHALL 以 `RoleRequest` 驗證：`name` 必填、最長 100 字元且不得與既有角色重複；`description` 選填、最長 255 字元；`default_route` 必填且須存在於 `permissions.name`；`permissions` 必填陣列、至少 1 項且每項須為存在的 permission id。角色建立與權限同步 SHALL 在單一 database transaction 內完成。成功 SHALL 302 導向 `/roles` 並帶 success flash「角色已建立」。

#### Scenario: 成功建立角色
- **GIVEN** 持有 `Role.create` 權限的使用者
- **WHEN** POST `/roles` 帶合法的 name、default_route 與 permissions
- **THEN** 系統回應 302 導向 `/roles`
- **AND** 角色已存在於資料庫

#### Scenario: 缺少必填欄位
- **WHEN** POST `/roles` 不帶任何欄位
- **THEN** session errors 包含 `name`、`default_route` 與 `permissions`

#### Scenario: 角色名稱重複
- **GIVEN** 已存在名為 `Admin` 的角色
- **WHEN** POST `/roles` 帶 `name=Admin`
- **THEN** session errors 包含 `name`

#### Scenario: permissions 含不存在的 id
- **WHEN** POST `/roles` 的 `permissions` 陣列含不存在的 permission id
- **THEN** session errors 包含 `permissions.0`
- **AND** 角色未被建立

#### Scenario: default_route 不存在於 permissions
- **WHEN** POST `/roles` 帶不存在的 `default_route`
- **THEN** session errors 包含 `default_route`

---

### Requirement: default_route 須可解析至命名路由

`default_route` 除 `exists:permissions,name` 外，系統 SHALL 以 `PermissionRouteResolver` 驗證該 permission 能解析到實際存在的命名路由——permission 存在於 DB 不代表對應的 controller method 仍存在，若不驗證，refactor 後使用者會被導向不存在的頁面而卡死。

#### Scenario: 無對應路由的 permission 被拒
- **GIVEN** 一個存在於 `permissions` 但無對應命名路由的 permission
- **WHEN** POST `/roles` 將其設為 `default_route`
- **THEN** session errors 包含 `default_route`（「所選的預設頁面尚未對應到任何路由。」）

---

### Requirement: default_route 權限自動補入

建立或更新角色時，系統 SHALL 自動將 `default_route` 對應的 permission 加入該角色的權限清單（即使表單未勾選），確保角色一定擁有其預設頁面的存取權。此為 Service 層自動補入設計，取代舊版「驗證 permissions 須包含 default_route」的拒絕式設計。

#### Scenario: 建立時自動補入
- **WHEN** POST `/roles` 的 `permissions` 未包含 `default_route` 對應的 permission
- **THEN** 建立後該角色仍擁有 `default_route` 對應的 permission

#### Scenario: 更新時自動補入
- **GIVEN** 既有角色
- **WHEN** PUT `/roles/{id}` 的 `permissions` 未包含 `default_route` 對應的 permission
- **THEN** 更新後該角色仍擁有 `default_route` 對應的 permission

---

### Requirement: 編輯角色

`GET /roles/{id}/edit` 與 `PUT /roles/{id}`（皆需 `Role.update` 權限）SHALL 提供角色編輯。驗證規則同新增（`name` unique 排除自身）。更新與權限同步 SHALL 在單一 database transaction 內完成。id 不存在時 SHALL 302 導向 `/roles` 並帶 error flash「找不到該角色」（redirect-with-flash UX，刻意不採 route model binding 的 404）。

#### Scenario: 成功更新角色
- **GIVEN** 持有 `Role.update` 權限的使用者與既有角色
- **WHEN** PUT `/roles/{id}` 帶合法欄位
- **THEN** 系統回應 302 導向 `/roles`
- **AND** 角色資料已更新

#### Scenario: 編輯不存在的角色
- **WHEN** GET `/roles/99999/edit`
- **THEN** 系統回應 302 導向 `/roles`
- **AND** session 含 error flash「找不到該角色」

#### Scenario: 更新不存在的角色
- **WHEN** PUT `/roles/99999` 帶合法欄位
- **THEN** 系統回應 302 導向 `/roles`
- **AND** session 含 error flash「找不到該角色」

---

### Requirement: 刪除角色

`DELETE /roles/{id}`（需 `Role.delete` 權限，前端以 axios 呼叫）SHALL 回應 JSON。成功時 SHALL 於單一 database transaction 內先清除 `role_has_permissions` pivot 再刪除角色，回應 200 與訊息「角色已刪除」。角色仍有使用者時 SHALL 拒絕並回應 422 與訊息「此角色仍有使用者，無法刪除」。id 不存在時回應 422 與訊息「找不到該角色」（與業務拒絕同碼；2026-06-12 設計 review 裁示維持現狀、不改 404）。

#### Scenario: 成功刪除角色
- **GIVEN** 無使用者的角色
- **WHEN** DELETE `/roles/{id}`
- **THEN** 系統回應 200 JSON「角色已刪除」
- **AND** 角色已自資料庫移除

#### Scenario: 刪除後 pivot 清空
- **GIVEN** 擁有權限關聯的角色
- **WHEN** DELETE `/roles/{id}` 成功
- **THEN** `role_has_permissions` 不再有該角色的關聯列

#### Scenario: 仍有使用者的角色拒絕刪除
- **GIVEN** 仍有使用者隸屬的角色
- **WHEN** DELETE `/roles/{id}`
- **THEN** 系統回應 422 JSON「此角色仍有使用者，無法刪除」
- **AND** 角色仍存在於資料庫

#### Scenario: 刪除不存在的角色
- **WHEN** DELETE `/roles/99999`
- **THEN** 系統回應 422 JSON「找不到該角色」

---

### Requirement: 權限解析

`CheckPermission` middleware SHALL 依下列順序解析 route 所需權限：(1) controller method 上的 `#[RequiresPermission]` attribute（優先，CRUD aliases 如 `store→Role.create`、`edit→Role.update` 以此明確宣告）、(2) fallback 以 `{Module}.{method}` 自動推導（controller 類名去除 `Controller` 後綴）。closure route 無 controller 可解析，SHALL 一律 403。

#### Scenario: attribute 宣告的權限生效
- **GIVEN** 持有 `Role.create` 權限的使用者
- **WHEN** GET `/roles/create`（`#[RequiresPermission('Role.create')]`）
- **THEN** 系統回應 200

#### Scenario: 自動推導 fallback
- **GIVEN** 持有 `Dashboard.index` 權限的使用者
- **WHEN** GET `/`（`DashboardController@index` → 推導為 `Dashboard.index`）
- **THEN** 系統回應 200

#### Scenario: closure route 一律 403
- **GIVEN** 已登入的 Admin 使用者
- **WHEN** GET 一個掛在 `permission` middleware 內的 closure route
- **THEN** 系統回應 403

---

### Requirement: 權限不足導向

受 `permission` middleware 保護的 route：使用者無角色（`role_id` 為 null）時 SHALL 302 導向 `no-role` 提示頁；有角色但無該權限時 SHALL 302 導向其角色 `default_route` 對應的頁面；當 `default_route` 即為當前所需權限、或無法解析至命名路由時 SHALL 403。寫入操作（POST/PUT/DELETE）SHALL 受與讀取相同的權限檢查。

#### Scenario: 無角色使用者
- **GIVEN** `role_id` 為 null 的使用者
- **WHEN** GET `/`
- **THEN** 系統回應 302 導向 `no-role` 提示頁

#### Scenario: 無權限導向預設頁
- **GIVEN** 僅持有 `Dashboard.index` 的使用者（default_route 為 `Dashboard.index`）
- **WHEN** GET `/roles`
- **THEN** 系統回應 302 導向 `/`

#### Scenario: Viewer 存取非 index 頁被導走
- **GIVEN** Viewer 角色（僅 index 權限）
- **WHEN** GET `/roles/create`
- **THEN** 系統回應 302（導向 default_route）

#### Scenario: default_route 即當前權限時 403
- **GIVEN** 角色 default_route 為 `Role.index` 但未擁有該權限
- **WHEN** GET `/roles`
- **THEN** 系統回應 403

#### Scenario: 寫入操作同樣被擋
- **GIVEN** Viewer 角色（無 `Role.create` / `Role.update` / `Role.delete`）
- **WHEN** POST `/roles`、PUT `/roles/{id}`、DELETE `/roles/{id}`
- **THEN** 三者皆 302 導向 default_route
- **AND** 資料庫無任何變更

---

### Requirement: Session 權限快取與即時撤銷

使用者權限 SHALL 快取於 session（`auth.permissions`），權限檢查不查 DB。系統 SHALL 以版本戳 `max(users.updated_at, roles.updated_at)`（直接走 DB 查詢，不依賴 Eloquent 屬性快取）偵測異動：角色權限被同步時 `RoleRepository::syncPermissions()` SHALL touch 該角色；middleware 偵測 session 版本戳 stale 時 SHALL 自動 reload 權限，使管理者的異動於受影響使用者**下一次 request** 生效，無需重新登入。已知限制：未掛 `permission` middleware 的場景（如 layout sidebar）依賴登入時載入的 session，stale 至下一次進入 protected route 才修正。

#### Scenario: 角色權限被拔即時生效
- **GIVEN** 已登入且可存取 `/roles` 的使用者
- **WHEN** 管理者移除其角色的 `Role.index` 權限（觸發 `roles.updated_at` 前進）後，該使用者再次 GET `/roles`
- **THEN** 同一 session 即被 302 導向 default_route，無需重新登入

#### Scenario: 使用者被換角色即時生效
- **GIVEN** 已登入的 Admin 使用者
- **WHEN** 管理者將其 `role_id` 改為僅有 `Dashboard.index` 的角色後，該使用者再次 GET `/roles`
- **THEN** 同一 session 即被 302 導向 default_route

---

### Requirement: UI 權限顯示控制

Blade SHALL 以 `<x-permission name="Module.action">` component 控制 UI 元素顯示：僅在登入者擁有該權限（session 快取判斷）時渲染 slot 內容。角色列表的「新增角色」「編輯」「刪除」入口 SHALL 分別以 `Role.create` / `Role.update` / `Role.delete` 包裹。

#### Scenario: 無權限隱藏操作入口
- **GIVEN** Viewer 角色（僅 index 權限）
- **WHEN** 檢視角色列表頁
- **THEN** 不渲染「新增角色」「編輯」「刪除」入口（視圖層行為，由 blade template 保證）

---

### Requirement: 權限與預設角色 Seeder

`PermissionSeeder` SHALL 以宣告式 `$modules` 陣列定義全部 `Module.Action` 權限並可重複執行（`updateOrCreate`）：不在清單中的 orphan permissions SHALL 被移除（含 pivot detach）；SHALL 建立 `Admin`（全部權限）與 `Viewer`（僅 index 權限）預設角色（`firstOrCreate`），且兩者的 `default_route` 對應權限 SHALL 被包含，對應 permission 不存在時 SHALL 拋出例外。

#### Scenario: 重複執行 seeder 收斂
- **GIVEN** 已執行過 seeder 的資料庫
- **WHEN** 再次執行 `PermissionSeeder`
- **THEN** 權限不重複建立、orphan permissions 被移除、Admin/Viewer 權限被重新同步

---

### Requirement: 無自我提權防護（刻意設計）

系統 MUST NOT 阻擋持有 `Role.update` 權限的使用者編輯自身所屬角色的權限（含 `User.update` 持有者調整自身 `role_id`）。此為刻意設計：內部後台、非公網暴露，能登入後台者即為受信任的內部人員（2026-05 角色安全審查裁定，原 `spec/15-role-security-review.md`）。同理 MUST NOT 引入 `is_system` 系統角色保護旗標。

#### Scenario: 持有 Role.update 者可編輯自身角色
- **GIVEN** 持有 `Role.update` 權限的使用者
- **WHEN** PUT `/roles/{id}` 編輯自身所屬角色的權限
- **THEN** 系統正常接受，不做額外阻擋

---

### Requirement: 不引入外部權限／稽核套件（刻意設計）

權限系統 MUST NOT 引入 `spatie/laravel-permission` 等外部權限套件（專案規模小、單一 guard、單一角色，自建 3 張表已足夠），MUST NOT 引入 `spatie/laravel-activitylog` 等操作稽核機制（內部後台暫無追溯需求，2026-05 安全審查裁定），權限檢查 MUST NOT 使用 `Gate::before`（檢查集中於 `CheckPermission` middleware 單一入口）。

#### Scenario: composer 依賴不含權限/稽核套件
- **WHEN** 檢視 `composer.json`
- **THEN** 不存在 `spatie/laravel-permission` 與 `spatie/laravel-activitylog` 依賴
