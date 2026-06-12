# grade-management Specification

## Purpose
版本（Grade）管理——CRU、權重排序與唯一性、狀態切換及停用後引用規則——的完整行為規格。版本不支援刪除，僅以 `status` 切換啟用／關閉，確保 shop / bill / addon 的參照穩定性。威脅模型：內部後台、非公網暴露；能登入後台者即為受信任的內部人員。合併自舊版 `spec/5-grades-system.md`、`spec/11-grade-hierarchy.md`、spec/7 的 grades 部分，與 2026-06-12 設計 review 修正（commit `934c2c1`、`9129b44`、`16890c1`、`e92d71f`）。

## Requirements
### Requirement: 版本列表

系統 SHALL 在 `GET /grades`（需 `Grade.index` 權限）顯示所有版本，依 `weight` 降序排列（數值越高等級越高），每筆 SHALL 含代碼、名稱、權重、價格與狀態。

#### Scenario: 有權限者可見版本列表
- **GIVEN** 持有 `Grade.index` 權限的使用者
- **WHEN** GET `/grades`
- **THEN** 系統回應 200 並渲染版本列表

#### Scenario: 依權重降序排列
- **GIVEN** 權重分別為 10、90、50 的三個版本
- **WHEN** GET `/grades`
- **THEN** 列表依 90、50、10 的順序呈現

---

### Requirement: 新增版本

`POST /grades`（需 `Grade.create` 權限）SHALL 以 `GradeRequest` 驗證：`code` 與 `name` 必填、最長 30 字元、僅限中英數與底線（regex）、各自於 grades 表內唯一；`price` 必填整數且不可小於 2；`weight` 必填整數、不可小於 1 且唯一（DB 層另有 unique index 防繞過，欄位無 default 值）；`status` 必填且須為 `GradeStatus` enum（1=啟用、0=關閉）。成功 SHALL 302 導向 `/grades` 並帶 success flash「版本已建立」。

#### Scenario: 成功建立版本
- **GIVEN** 持有 `Grade.create` 權限的使用者
- **WHEN** POST `/grades` 帶合法的 code、name、price、weight、status
- **THEN** 系統回應 302 導向 `/grades` 並帶 success flash
- **AND** 版本已存在於資料庫

#### Scenario: code 重複
- **GIVEN** 已存在 code 相同的版本
- **WHEN** POST `/grades` 帶重複 code
- **THEN** session errors 包含 `code`

#### Scenario: name 重複
- **GIVEN** 已存在 name 相同的版本
- **WHEN** POST `/grades` 帶重複 name
- **THEN** session errors 包含 `name`

#### Scenario: weight 重複
- **GIVEN** 已存在 weight 相同的版本
- **WHEN** POST `/grades` 帶重複 weight
- **THEN** session errors 包含 `weight`

#### Scenario: code 或 name 含特殊字元
- **WHEN** POST `/grades` 的 code 或 name 含 `!@#` 等中英數與底線以外的字元
- **THEN** session errors 包含對應欄位

#### Scenario: price 低於下限
- **WHEN** POST `/grades` 帶 `price=1` 或負數
- **THEN** session errors 包含 `price`

#### Scenario: weight 缺失或低於 1
- **WHEN** POST `/grades` 未帶 weight，或帶 `weight=0`
- **THEN** session errors 包含 `weight`

#### Scenario: status 非法值
- **WHEN** POST `/grades` 帶 `status=99`
- **THEN** session errors 包含 `status`

---

### Requirement: 編輯版本

`GET /grades/{id}/edit` 與 `PUT /grades/{id}`（皆需 `Grade.update` 權限）SHALL 提供版本編輯，編輯表單 SHALL 預填既有值。驗證規則同新增（code / name / weight 的 unique 排除自身）。id 不存在時 SHALL 302 導向 `/grades` 並帶 error flash「找不到該版本」（redirect-with-flash UX，刻意不採 route model binding 的 404，源自 spec/7 設計）。

#### Scenario: 成功更新版本
- **GIVEN** 持有 `Grade.update` 權限的使用者與既有版本
- **WHEN** PUT `/grades/{id}` 帶合法欄位（含 status 變更）
- **THEN** 系統回應 302 導向 `/grades`
- **AND** 版本資料已更新

#### Scenario: 編輯表單預填既有值
- **GIVEN** 既有版本
- **WHEN** GET `/grades/{id}/edit`
- **THEN** 系統回應 200，表單含該版本的 code、name、price

#### Scenario: unique 驗證排除自身
- **GIVEN** 既有版本
- **WHEN** PUT `/grades/{id}` 帶與自身相同的 code、name、weight
- **THEN** 驗證通過，無 session errors

#### Scenario: 編輯不存在的版本
- **WHEN** GET `/grades/99999/edit`
- **THEN** 系統回應 302 導向 `/grades`
- **AND** session 含 error flash「找不到該版本」

#### Scenario: 更新不存在的版本
- **WHEN** PUT `/grades/99999` 帶合法欄位
- **THEN** 系統回應 302 導向 `/grades`
- **AND** session 含 error flash「找不到該版本」

---

### Requirement: 狀態切換

`PATCH /grades/{id}/toggle`（需 `Grade.update` 權限，前端以 axios + 確認 modal 呼叫）SHALL 回應 JSON：成功時切換 `status`（Active ↔ Inactive）並回應 200 與訊息「版本狀態已更新」；id 不存在時回應 422 與訊息「找不到該版本」。

#### Scenario: 啟用版本切換為關閉
- **GIVEN** 啟用中的版本
- **WHEN** PATCH `/grades/{id}/toggle`
- **THEN** 系統回應 200，版本 status 變為 Inactive

#### Scenario: 關閉版本切換為啟用
- **GIVEN** 關閉中的版本
- **WHEN** PATCH `/grades/{id}/toggle`
- **THEN** 系統回應 200，版本 status 變為 Active

#### Scenario: 切換不存在的版本
- **WHEN** PATCH `/grades/99999/toggle`
- **THEN** 系統回應 422 JSON「找不到該版本」

#### Scenario: 無權限者不可切換
- **GIVEN** Viewer 角色（無 `Grade.update`）
- **WHEN** PATCH `/grades/{id}/toggle`
- **THEN** 系統回應 302（導向 default_route）
- **AND** 版本 status 不變

---

### Requirement: 權重即時檢查端點

`GET /grades/check-weight`（需 `Grade.update` 權限，供新增/編輯表單即時驗證與位置預覽）SHALL 回應 JSON：`duplicate`（該 weight 是否已被使用）、`conflicting_grade`（衝突版本的 id / name / weight，無衝突為 null）、`grades`（全部版本的 id / name / weight，依 weight 降序）。帶 `exclude_id` 時 SHALL 豁免該 id 自身；`weight` 小於 1 時 SHALL 直接回應 `duplicate=false`、`grades=[]`，不查詢資料庫。

#### Scenario: 權重已被使用
- **GIVEN** 已存在 weight=77 的版本
- **WHEN** GET `/grades/check-weight?weight=77`
- **THEN** 回應 `duplicate=true` 且 `conflicting_grade` 為該版本

#### Scenario: 權重未被使用
- **GIVEN** 已存在 weight=77 的版本
- **WHEN** GET `/grades/check-weight?weight=78`
- **THEN** 回應 `duplicate=false`、`conflicting_grade=null`，`grades` 含既有版本

#### Scenario: exclude_id 豁免自身
- **GIVEN** weight=77 的版本
- **WHEN** GET `/grades/check-weight?weight=77&exclude_id={自身 id}`
- **THEN** 回應 `duplicate=false`

#### Scenario: weight 低於 1 回空結果
- **WHEN** GET `/grades/check-weight?weight=0`
- **THEN** 回應 `duplicate=false`、`conflicting_grade=null`、`grades=[]`

#### Scenario: 無權限者被擋
- **GIVEN** Viewer 角色（無 `Grade.update`）
- **WHEN** GET `/grades/check-weight?weight=77`
- **THEN** 系統回應 302（導向 default_route）

---

### Requirement: 停用版本引用規則

停用版本 MUST NOT 影響既有引用：已關聯該版本的 shop / bill detail / addon SHALL 維持原樣，不被回頭驗證或自動清空（否則管理員僅修改其他欄位也會被驗證擋住）。新建立的引用 SHALL 被擋下，豁免邊界依功能而異：`ShopUpdateRequest` 變更為停用版本 → 422，維持原版本（即使已停用）→ 允許；`StoreBillRequest` 任何停用版本 → 422，無豁免；`AddonRequest` 新關聯停用版本 → 422，既有關聯保留 → 允許。

#### Scenario: Shop 變更為停用版本被拒
- **GIVEN** 一個停用版本
- **WHEN** PUT `/shops/{id}` 將 `grade_id` 變更為該版本
- **THEN** session errors 包含 `grade_id`

#### Scenario: Shop 維持原（已停用）版本允許
- **GIVEN** shop 既有版本已被停用
- **WHEN** PUT `/shops/{id}` 維持原 `grade_id` 並修改其他欄位
- **THEN** 驗證通過，更新成功

#### Scenario: 新 Bill 使用停用版本被拒
- **GIVEN** 一個停用版本
- **WHEN** POST bill 的 `details.*.grade_id` 含該版本
- **THEN** 驗證失敗，bill 未建立

#### Scenario: Addon 新關聯停用版本被拒、既有關聯保留允許
- **GIVEN** 一個停用版本
- **WHEN** addon 的 `grade_ids` 新增該版本 → 驗證失敗；addon 既有關聯的版本後來被停用，更新時保留該關聯 → 驗證通過
- **THEN** 僅新指派被擋，既有關聯不受影響

---

### Requirement: UI 權限顯示控制

版本列表 SHALL 依登入者權限控制操作入口：「新增版本」以 `<x-permission name="Grade.create">`、「編輯」以 `<x-permission name="Grade.update">` 包裹；狀態切換鈕以 `hasPermissionTo('Grade.update')` 判斷——有權限渲染可點擊 toggle switch，無權限渲染純狀態 badge（`<x-permission>` 無 else slot，此為顯示替代內容的刻意作法）。無 `Grade.create` 權限者存取 `GET /grades/create` SHALL 被 302 導向其 default_route。

#### Scenario: Viewer 不可存取新增頁
- **GIVEN** Viewer 角色（僅 index 權限）
- **WHEN** GET `/grades/create`
- **THEN** 系統回應 302（導向 default_route）

#### Scenario: 無權限者見純狀態 badge
- **GIVEN** Viewer 角色檢視版本列表
- **WHEN** 渲染狀態欄
- **THEN** 顯示不可點擊的狀態 badge，不渲染 toggle switch 與「新增」「編輯」入口（視圖層行為，由 blade template 保證）

---

### Requirement: 預設版本 Seeder

`GradeSeeder` SHALL 以 `code` 為唯一鍵 `updateOrCreate` 建立 6 個預設版本（版本S `grade_s` 10000/100、版本A `grade_a` 9000/85、版本B `grade_b` 8000/70、版本C `grade_c` 7000/55、版本D `grade_d` 6000/40、版本E `grade_e` 5000/25，皆為啟用），並 SHALL 可重複執行（冪等）。

#### Scenario: 重複執行 seeder 收斂
- **GIVEN** 已執行過 seeder 的資料庫
- **WHEN** 再次執行 `GradeSeeder`
- **THEN** 版本不重複建立，name / price / weight 被重新同步為預設值

---

### Requirement: 無刪除功能（刻意設計）

系統 MUST NOT 提供版本刪除：無 `DELETE /grades/{id}` 路由、Controller 無 `destroy` 方法、權限模組 MUST NOT 包含 `Grade.delete` action（`PermissionSeeder` 的 Grade 模組僅 index / create / update）。此為刻意設計：版本被 shop / bill detail / addon 引用，刪除會破壞參照穩定性（spec/5 原始決策）；生命週期終結以停用（status=Inactive）表達。

#### Scenario: DELETE 路由不存在
- **GIVEN** Admin 使用者與既有版本
- **WHEN** DELETE `/grades/{id}`
- **THEN** 系統回應 405 Method Not Allowed

