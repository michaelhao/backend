## ADDED Requirements

### Requirement: Conference 資料模型

系統 SHALL 維護 `conferences` 資料表，記錄說明會的名稱、狀態、活動時間窗與報名時間窗。欄位包含：`id`（int unsigned 主鍵）、`name`（字串，最長 100）、`status`（Active=1 或 Inactive=0）、`started_at`、`ended_at`、`register_started_at`、`register_ended_at`（皆為 datetime）、`created_at`、`updated_at`。資料表 MUST 對 `status` 與 `started_at` 建立索引。

#### Scenario: 執行 migration 建立資料表
- **WHEN** 執行 `php artisan migrate`
- **THEN** 資料庫出現 `conferences` 資料表，欄位與型別與上述規格相符
- **AND** `status` 與 `started_at` 索引已建立

#### Scenario: Model 正確 cast status 為 enum
- **WHEN** 讀取一筆 `Conference` 記錄
- **THEN** `$conference->status` 為 `App\Enums\ConferenceStatus` 的實例

### Requirement: 列表檢視

系統 SHALL 提供 `GET /conferences` 列表頁，顯示所有說明會（包含 Active 與 Inactive），並支援依關鍵字（`keyword`）搜尋 `name`、依 `status` 篩選、與 `per_page` 分頁。頁面 MUST 受 `Conference.index` 權限保護。`per_page` SHALL 限定於白名單 `{50, 100, 150, 200}`；不在白名單或未提供時 SHALL fallback 為 `50`。

#### Scenario: 授權使用者瀏覽列表
- **WHEN** 擁有 `Conference.index` 權限的使用者 GET `/conferences`
- **THEN** 回傳 HTTP 200 與包含分頁結果的 `admin.conferences.index` view

#### Scenario: 未授權使用者被擋
- **WHEN** 不具 `Conference.index` 權限的使用者 GET `/conferences`
- **THEN** 系統拒絕存取（依專案權限 middleware 回應 403 或導向）

#### Scenario: 依關鍵字過濾
- **WHEN** 使用者 GET `/conferences?keyword=春季`
- **THEN** 回傳結果僅包含 `name` 含「春季」的說明會

#### Scenario: 依狀態過濾
- **WHEN** 使用者 GET `/conferences?status=0`
- **THEN** 回傳結果僅包含 `status = Inactive` 的說明會

#### Scenario: per_page 白名單外的值 fallback 為 50
- **WHEN** 使用者 GET `/conferences?per_page=9999`
- **THEN** 回傳分頁大小為 `50`，不以使用者輸入為準

### Requirement: 新增說明會

系統 SHALL 提供 `GET /conferences/create` 顯示表單、`POST /conferences` 接收表單並建立新說明會。兩者 MUST 受 `Conference.create` 權限保護。所有欄位 MUST 通過驗證後才寫入資料庫。建立成功後 SHALL 導向 `/conferences` 並帶 success flash 訊息。

#### Scenario: 成功建立
- **WHEN** 授權使用者 POST 合法表單（name、status、四個時間欄位皆合法且順序正確）
- **THEN** 資料庫新增一筆 `conferences` 記錄
- **AND** 回應為 302 導向 `conferences.index`
- **AND** session 含成功訊息

#### Scenario: 必填欄位缺漏時不寫入
- **WHEN** 授權使用者 POST 缺少 `name` 或 `status` 或任一時間欄位的表單
- **THEN** 回應為 422（或 redirect back with errors）
- **AND** 資料庫未新增任何 `conferences` 記錄

#### Scenario: 時間順序錯誤時不寫入
- **WHEN** 授權使用者 POST 時間欄位不滿足順序規則的表單
- **THEN** 回應為 422（或 redirect back with errors）
- **AND** 資料庫未新增任何 `conferences` 記錄

### Requirement: 修改說明會

系統 SHALL 提供 `GET /conferences/{id}/edit` 顯示編輯表單、`PUT /conferences/{id}` 接收更新。兩者 MUST 受 `Conference.update` 權限保護。更新成功後 SHALL 導向 `/conferences` 並帶 success flash 訊息。找不到指定 `id` 時 SHALL 導回 `/conferences` 並帶 error flash 訊息。

#### Scenario: 成功更新
- **WHEN** 授權使用者 PUT 合法表單至 `/conferences/{existing_id}`
- **THEN** 該筆記錄欄位被更新
- **AND** 回應為 302 導向 `conferences.index` 並帶 success flash

#### Scenario: 找不到 id
- **WHEN** 授權使用者 GET `/conferences/{non_existing_id}/edit`
- **THEN** 回應為 302 導向 `conferences.index` 並帶 error flash

#### Scenario: 驗證失敗不變更資料
- **WHEN** 授權使用者 PUT 時間順序錯誤的表單
- **THEN** 資料庫該筆記錄保持原狀
- **AND** 回應為 422 或 redirect back with errors

### Requirement: 時間欄位順序驗證

系統 SHALL 在寫入（建立或修改）時驗證四個時間欄位滿足順序：`register_started_at < register_ended_at ≤ started_at < ended_at`。任一條件不滿足 SHALL 視為驗證錯誤。

#### Scenario: 報名截止晚於活動開始
- **WHEN** 使用者提交 `register_ended_at > started_at`
- **THEN** 系統回傳驗證錯誤，不進行寫入

#### Scenario: 活動結束不晚於活動開始
- **WHEN** 使用者提交 `ended_at ≤ started_at`
- **THEN** 系統回傳驗證錯誤，不進行寫入

#### Scenario: 報名開始晚於報名截止
- **WHEN** 使用者提交 `register_started_at ≥ register_ended_at`
- **THEN** 系統回傳驗證錯誤，不進行寫入

#### Scenario: 報名截止等於活動開始
- **WHEN** 使用者提交 `register_ended_at = started_at`（其餘順序皆合法）
- **THEN** 系統接受，寫入成功

### Requirement: 不提供刪除功能

為保留歷史紀錄，系統 SHALL NOT 提供任何刪除說明會的路徑：無 DELETE 路由、無 `destroy` controller action、無 `Conference.delete` 權限、無軟刪欄位。下架 SHALL 透過 `status = Inactive` 達成。

#### Scenario: DELETE 路由不存在
- **WHEN** 任何使用者 DELETE `/conferences/{id}`
- **THEN** 系統回應 405（Method Not Allowed）或 404

#### Scenario: 權限列表不含 Conference.delete
- **WHEN** 查詢系統已註冊之權限清單
- **THEN** 清單中不存在 `Conference.delete`
