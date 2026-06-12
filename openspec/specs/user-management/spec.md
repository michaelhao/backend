# user-management Specification

## Purpose
後台使用者帳號 CRUD、密碼強度政策、自我保護規則與刪除參照檢查的完整行為規格。威脅模型：內部後台、非公網暴露（brute-force 不在 scope）、能登入後台者即為受信任的內部人員；正式環境無對外網際網路連線（禁用 HIBP 等外部 API）。合併自舊版 `spec/4-user-system.md`、`spec/7-edit-route-id.md`（users 部分）、`spec/16-users-security-review.md`（users 部分 P1-1/P1-2）與 2026-06-12 設計 review 修正（commit `898c597`、`4708b1a`、`f65d773`、`30c45d8`）。

## Requirements
### Requirement: 使用者列表

系統 SHALL 在 `GET /users`（需 `User.index` 權限）顯示所有使用者，依建立時間新到舊排列，每筆 SHALL 含名稱、電子郵件與角色名稱（eager load `role`，無角色時顯示「—」）。

#### Scenario: 有權限者可見使用者列表
- **GIVEN** 持有 `User.index` 權限的使用者
- **WHEN** GET `/users`
- **THEN** 系統回應 200 並渲染使用者列表

---

### Requirement: 權限控管

六條使用者管理路由 SHALL 受 `auth` middleware 與 `RequiresPermission` attribute 保護：index 需 `User.index`、create/store 需 `User.create`、edit/update 需 `User.update`、destroy 需 `User.delete`。未登入或無對應權限者 SHALL 被導離，且資料不得被變更。

#### Scenario: 未登入者被導向登入頁
- **WHEN** 訪客 GET `/users`
- **THEN** 系統回應 302 導向 `/login`

#### Scenario: 無 create 權限者不可見新增頁
- **GIVEN** 僅持有 Viewer 角色權限的使用者
- **WHEN** GET `/users/create`
- **THEN** 系統回應 302 導離

#### Scenario: 無 update 權限者不可更新使用者
- **GIVEN** 僅持有 Viewer 角色權限的使用者
- **WHEN** PUT `/users/{id}` 試圖改名
- **THEN** 系統回應 302 導離
- **AND** 該使用者名稱未被變更

#### Scenario: 無 delete 權限者不可刪除使用者
- **GIVEN** 僅持有 Viewer 角色權限的使用者
- **WHEN** DELETE `/users/{id}`
- **THEN** 系統回應 302 導離
- **AND** 該使用者仍存在於資料庫

---

### Requirement: 新增使用者

`POST /users`（需 `User.create` 權限）SHALL 以 `StoreUserRequest` 驗證：`name` 必填、最長 100 字元；`email` 必填、email 格式、最長 255 字元且不得與既有使用者重複；`password` 必填、須通過確認欄位（`confirmed`）並符合密碼強度規則；`role_id` 必填且須存在於 `roles` 表。成功 SHALL 建立使用者（密碼經 `hashed` cast 雜湊）、302 導向 `/users` 並帶 success flash「使用者已建立」。

#### Scenario: 成功建立使用者
- **GIVEN** 持有 `User.create` 權限的使用者
- **WHEN** POST `/users` 帶合法的 name、email、password（含確認）與 role_id
- **THEN** 系統回應 302 導向 `/users` 並帶 success flash
- **AND** 使用者已存在於資料庫且綁定指定角色

#### Scenario: email 重複被拒
- **GIVEN** 已存在使用該 email 的使用者
- **WHEN** POST `/users` 帶相同 email
- **THEN** session errors 包含 `email`

#### Scenario: 未填密碼被拒
- **WHEN** POST `/users` 不帶 password
- **THEN** session errors 包含 `password`

#### Scenario: 密碼確認不一致被拒
- **WHEN** POST `/users` 的 password 與 password_confirmation 不同
- **THEN** session errors 包含 `password`

#### Scenario: role_id 不存在被拒
- **WHEN** POST `/users` 帶不存在的 role_id
- **THEN** session errors 包含 `role_id`

#### Scenario: name 超過 100 字元被拒
- **WHEN** POST `/users` 帶 101 字元的 name
- **THEN** session errors 包含 `name`

---

### Requirement: 密碼強度規則

使用者密碼 SHALL 符合 `PasswordPolicy::default()` 全站政策（定義於 `app/Rules/PasswordPolicy.php`，各 FormRequest 明確呼叫）：最少 12 字元、含大小寫字母、數字與符號。系統 MUST NOT 使用 `Password::uncompromised()`（HIBP 外部 API）——正式環境無對外網際網路連線，外部呼叫必定失敗。

#### Scenario: 新增時弱密碼被拒
- **WHEN** POST `/users` 帶純小寫字母的密碼
- **THEN** session errors 包含 `password`

#### Scenario: 更新時弱密碼被拒
- **GIVEN** 既有使用者
- **WHEN** PUT `/users/{id}` 帶純小寫字母的密碼
- **THEN** session errors 包含 `password`

---

### Requirement: 編輯與更新使用者

`GET /users/{id}/edit`（需 `User.update` 權限）SHALL 渲染預填 name、email、role_id 的編輯表單。`PUT /users/{id}` SHALL 以 `UpdateUserRequest` 驗證，規則同新增，除了：`email` 唯一性檢查 SHALL 排除自身（`ignore($this->route('id'))`）；`password` 選填——留空 SHALL 維持原密碼不變（空值由 Service 層剝除），有值則須通過確認欄位與密碼強度規則。成功 SHALL 302 導向 `/users` 並帶 success flash「使用者已更新」。

#### Scenario: 編輯表單預填既有資料
- **GIVEN** 既有使用者
- **WHEN** GET `/users/{id}/edit`
- **THEN** 系統回應 200 且表單含該使用者的 name 與 email

#### Scenario: 密碼留空維持不變
- **GIVEN** 既有使用者
- **WHEN** PUT `/users/{id}` 改名且 password 留空
- **THEN** 名稱已更新
- **AND** 密碼雜湊值維持原值

#### Scenario: 填入新密碼則更新
- **GIVEN** 既有使用者
- **WHEN** PUT `/users/{id}` 帶合規新密碼與確認
- **THEN** 新密碼可通過 Hash 驗證

#### Scenario: email 改為他人 email 被拒
- **GIVEN** 兩個既有使用者
- **WHEN** PUT 更新其中一人的 email 為另一人的 email
- **THEN** session errors 包含 `email`

---

### Requirement: 找不到 id 的處理（刻意設計）

路由 SHALL 採手動 `{id}` 查詢（`UserService::findUserById()` 經 Repository），MUST NOT 使用 route model binding——找不到時以應用語意回應而非空白 404 頁（源自 spec/7 的設計決策）：edit 與 update SHALL 302 導回 `/users` 並帶 error flash「找不到該使用者」；destroy（axios JSON 請求）SHALL 回應 404 與 JSON 訊息「找不到該使用者」。

#### Scenario: 編輯不存在的使用者
- **WHEN** GET `/users/99999/edit`
- **THEN** 系統回應 302 導向 `/users` 並帶 error flash「找不到該使用者」

#### Scenario: 更新不存在的使用者
- **WHEN** PUT `/users/99999`
- **THEN** 系統回應 302 導向 `/users` 並帶 error flash「找不到該使用者」

#### Scenario: 刪除不存在的使用者
- **WHEN** DELETE `/users/99999`
- **THEN** 系統回應 404 與 JSON `{"message": "找不到該使用者"}`

---

### Requirement: 刪除使用者

`DELETE /users/{id}`（需 `User.delete` 權限）為 axios 請求（前端確認 modal 後送出），成功 SHALL 刪除該使用者、一併刪除其 `sessions` 表記錄（既有登入即刻失效；僅適用 `SESSION_DRIVER=database`），並回應 JSON 訊息「使用者已刪除」；前端隨之自列表移除該列。

#### Scenario: 成功刪除其他使用者
- **GIVEN** 持有 `User.delete` 權限的使用者
- **WHEN** DELETE `/users/{id}`（非自己）
- **THEN** 系統回應 200 與 JSON `{"message": "使用者已刪除"}`
- **AND** 該使用者已不存在於資料庫

#### Scenario: 刪除後 sessions 一併清除
- **GIVEN** 目標使用者於 `sessions` 表存在既有 session 記錄
- **WHEN** DELETE `/users/{id}`
- **THEN** `sessions` 表不再有該使用者的記錄

---

### Requirement: 刪除保護——帳單業務參照

使用者被 `bills.shop_sales_id` 參照（曾被指派為帳單業務）時，系統 MUST NOT 允許刪除，SHALL 回應 422 與 JSON 訊息「該使用者為帳單業務，無法刪除」。`bills.creator_id` 與帳單狀態紀錄的操作者屬歷史稽核資料，刻意不擋刪除（否則多數使用者將永遠無法刪除），顯示層以 nullsafe 處理懸掛參照。

#### Scenario: 帳單業務不可刪除
- **GIVEN** 某使用者被一筆帳單指派為業務（`shop_sales_id`）
- **WHEN** DELETE `/users/{id}`
- **THEN** 系統回應 422 與 JSON `{"message": "該使用者為帳單業務，無法刪除"}`
- **AND** 該使用者仍存在於資料庫

---

### Requirement: 自我保護規則

操作對象為目前登入的使用者時：系統 MUST NOT 允許刪除自己的帳號（回應 422 與訊息「無法刪除自己的帳號」）；MUST NOT 允許修改自己的 `role_id`（302 導回前頁並帶 error flash「無法修改自己的角色」）。自己的 name、email、password SHALL 仍可正常修改。此規則防止管理員把自己鎖出系統或意外降權（源自 spec/16 P1-1），由 `UserService` 強制執行（拋 `UserOperationException`）。

#### Scenario: 不可刪除自己
- **GIVEN** 持有 `User.delete` 權限的登入使用者
- **WHEN** DELETE 自己的 id
- **THEN** 系統回應 422 與 JSON `{"message": "無法刪除自己的帳號"}`
- **AND** 自己仍存在於資料庫

#### Scenario: 不可修改自己的角色
- **GIVEN** 持有 `User.update` 權限的登入使用者
- **WHEN** PUT 自己的 id 並帶不同的 role_id
- **THEN** 系統回應 302 並帶 error flash「無法修改自己的角色」
- **AND** 自己的 role_id 維持原值

#### Scenario: 可修改自己的名稱（角色不變）
- **GIVEN** 持有 `User.update` 權限的登入使用者
- **WHEN** PUT 自己的 id 改名、role_id 維持原值
- **THEN** 系統回應 302 導向 `/users` 並帶 success flash
- **AND** 名稱已更新

---

### Requirement: 無自助註冊與 email 驗證（刻意設計）

系統 MUST NOT 提供自助註冊端點，使用者帳號僅能由持 `User.create` 權限的管理員建立；MUST NOT 啟用 email 驗證流程（`MustVerifyEmail` 未實作）——內部後台，使用者即受信任的內部人員，帳號由管理員開立即視為有效。

#### Scenario: 無註冊路由
- **WHEN** 訪客 GET `/register`
- **THEN** 系統回應 404（路由不存在）

