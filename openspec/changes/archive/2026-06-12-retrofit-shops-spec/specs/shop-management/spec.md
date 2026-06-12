# shop-management Specification (delta)

## ADDED Requirements

### Requirement: 商店列表

系統 SHALL 在 `GET /shops`（需 `Shop.index` 權限）顯示商店分頁列表，依 id 升冪排列並 eager load `admin` 與 `grade`，每筆 SHALL 含商店名稱、版本名稱（無版本顯示「-」）、狀態 badge（中文 label 由 `ShopStatus::label()` 提供）與認證狀態。已認證（`business_number` 非 NULL）SHALL 顯示可點擊 badge，點擊開啟顯示統一編號與公司名稱的詳情 Modal；未認證 SHALL 顯示不可點擊的灰色 badge。

#### Scenario: 有權限者可見商店列表
- **GIVEN** 持有 `Shop.index` 權限的 Admin 使用者
- **WHEN** GET `/shops`
- **THEN** 系統回應 200 並渲染商店列表

#### Scenario: Viewer 具列表權限可見商店列表
- **GIVEN** 持有 Viewer 角色（含 `Shop.index`）的使用者
- **WHEN** GET `/shops`
- **THEN** 系統回應 200 並渲染商店列表

---

### Requirement: 權限控管

四條商店管理路由 SHALL 受 `auth` middleware 與 `RequiresPermission` attribute 保護：index 需 `Shop.index`、edit/update/certify 需 `Shop.update`。未登入或無對應權限者 SHALL 被導離，且資料不得被變更。

#### Scenario: 未登入者被導向登入頁
- **WHEN** 訪客 GET `/shops`
- **THEN** 系統回應 302 導向 `/login`

#### Scenario: 無 update 權限者不可見編輯頁
- **GIVEN** 僅持有 Viewer 角色權限的使用者
- **WHEN** GET `/shops/{id}/edit`
- **THEN** 系統回應 302 導離

---

### Requirement: 列表搜尋篩選

列表 SHALL 支援四個篩選條件且彼此 **交集**：`keyword` 對商店名稱模糊比對（`LIKE %v%`）；`grade_id` 精準比對；`business_number` 對管理員統一編號精準比對；`is_certified` 為 `'1'` 時篩選統一編號非 NULL、`'0'` 時篩選統一編號為 NULL。篩選值 SHALL 於表單與分頁連結間保留。

#### Scenario: 關鍵字模糊比對商店名稱
- **GIVEN** 名稱分別為「正品旗艦店」與「精選商行」的兩家商店
- **WHEN** GET `/shops?keyword=旗艦`
- **THEN** 列表僅含「正品旗艦店」

#### Scenario: 版本精準篩選
- **GIVEN** 隸屬不同版本的兩家商店
- **WHEN** GET `/shops?grade_id={id}`
- **THEN** 列表僅含該版本的商店

#### Scenario: 統一編號精準篩選
- **GIVEN** 管理員統編分別為 12345678 與 87654321 的兩家商店
- **WHEN** GET `/shops?business_number=12345678`
- **THEN** 列表僅含統編完全相符的商店

#### Scenario: 認證狀態篩選
- **GIVEN** 一家已認證（統編非 NULL）與一家未認證的商店
- **WHEN** GET `/shops?is_certified=1`（或 `=0`）
- **THEN** 列表僅含對應認證狀態的商店

#### Scenario: 多條件交集
- **GIVEN** 多家商店僅其一同時滿足 keyword 與 grade_id 條件
- **WHEN** GET `/shops?keyword=...&grade_id=...`
- **THEN** 列表僅含同時滿足所有條件的商店

---

### Requirement: 分頁與每頁筆數

列表 SHALL 分頁，`per_page` 僅接受白名單 `[50, 100, 150, 200]`，未帶或非法值 SHALL 回退 50。切換每頁筆數的隱藏表單 SHALL 帶上所有現存篩選值——判斷依據為「非 null 且非空字串」，falsy 但有意義的 `is_certified=0` MUST NOT 被遺漏。

#### Scenario: 合法 per_page 生效
- **WHEN** GET `/shops?per_page=100`
- **THEN** 每頁筆數為 100 且選單顯示 100 為選取狀態

#### Scenario: 非法 per_page 回退 50
- **WHEN** GET `/shops?per_page=999`
- **THEN** 每頁筆數為 50

#### Scenario: 切換每頁筆數保留未認證篩選
- **WHEN** GET `/shops?is_certified=0`
- **THEN** 每頁筆數表單含 `name="is_certified" value="0"` 的隱藏欄位

---

### Requirement: 編輯表單與資料遮蔽

`GET /shops/{id}/edit`（需 `Shop.update` 權限）SHALL 渲染單一表單、分「商店基本資料」與「商店管理員基本資料」兩區塊。管理員 email SHALL 解密後以 `Mask::email()` 遮蔽顯示（點「修改」才切換為可輸入）；統一編號 SHALL 以 `Mask::string()` 遮蔽顯示於 readonly 欄位；公司名稱為 readonly。狀態下拉 SHALL 由 `ShopStatus::cases()` 渲染並以 `label()` 顯示中文。

#### Scenario: 編輯表單預填並遮蔽敏感欄位
- **GIVEN** 既有商店與其管理員
- **WHEN** GET `/shops/{id}/edit`
- **THEN** 系統回應 200，表單含商店 name、email、grade、status 與管理員 name
- **AND** 管理員 email 以遮蔽形式顯示

---

### Requirement: 更新商店

`PUT /shops/{id}`（需 `Shop.update` 權限）SHALL 以 `ShopUpdateRequest` 驗證：`name` 必填、最長 50 字元；`email` 必填、email 格式且不得與其他商店重複（`unique` 排除自身 `ignore($this->route('id'))`）；`grade_id` 必填且須存在（變更時限 Active，見版本變更規則）；`status` 必填且須為合法 `ShopStatus` 值；`admin.name` 必填、最長 20 字元；`admin.email` 必填、email 格式；`admin.business_number` 選填、8 位數字；`admin.company_name` 選填。商店與管理員的更新 SHALL 在同一資料庫交易內完成；商店無管理員記錄時 SHALL 僅更新商店本身、不得出錯。成功 SHALL 302 導向 `/shops` 並帶 success flash「商店已更新」。

#### Scenario: 成功更新商店與管理員
- **GIVEN** 持有 `Shop.update` 權限的使用者
- **WHEN** PUT `/shops/{id}` 帶合法的商店與管理員欄位
- **THEN** 系統回應 302 導向 `/shops` 並帶 success flash
- **AND** shops 與 shops_admin 資料已更新

#### Scenario: 商店 email 與他店重複被拒
- **GIVEN** 兩家既有商店
- **WHEN** PUT 更新其中一家的 email 為另一家的 email
- **THEN** session errors 包含 `email`

#### Scenario: 商店 email 維持自身值允許
- **GIVEN** 既有商店
- **WHEN** PUT `/shops/{id}` 帶與自身相同的 email
- **THEN** 更新成功、無驗證錯誤

#### Scenario: status 非法值被拒
- **WHEN** PUT `/shops/{id}` 帶不在 ShopStatus 內的 status
- **THEN** session errors 包含 `status`

#### Scenario: grade_id 不存在被拒
- **WHEN** PUT `/shops/{id}` 帶不存在的 grade_id
- **THEN** session errors 包含 `grade_id`

#### Scenario: 無管理員的商店仍可更新
- **GIVEN** 一家沒有 shops_admin 記錄的商店
- **WHEN** PUT `/shops/{id}` 更新商店基本資料
- **THEN** 系統回應 302 並帶 success flash
- **AND** 商店資料已更新

---

### Requirement: 表單欄位白名單

更新 SHALL 僅寫入通過驗證的欄位：商店欄位取 `$request->safe()->only(['name','email','grade_id','status'])`、管理員欄位取 `validated()['admin']`。未在驗證規則內的欄位（如 `admin[password]`、`admin[shop_id]`）MUST NOT 經此表單寫入資料庫。

#### Scenario: 未驗證欄位被忽略
- **GIVEN** 既有商店管理員
- **WHEN** PUT `/shops/{id}` 額外夾帶 `admin[password]` 與 `admin[shop_id]`
- **THEN** 更新成功
- **AND** 管理員的 password 雜湊值與 shop_id 維持原值

---

### Requirement: 管理員 email 加密與應用層唯一性（刻意設計）

管理員 email SHALL 以 `encrypted` cast 加密儲存（密碼為 `hashed` cast）；DB 層 MUST NOT 對該欄位加 unique index——加密值不可比對。唯一性 SHALL 由 `ShopService` 於寫入前解密逐筆比對（排除自身），衝突時拋 `ValidationException`，errors 包含 `admin.email`（訊息「此 email 已被使用」）。O(n) 解密比對為 encrypted cast 的必然取捨，內部後台資料量可接受。

#### Scenario: 管理員 email 與他店管理員重複被拒
- **GIVEN** 兩家商店各有管理員
- **WHEN** PUT 更新其中一位管理員的 email 為另一位的 email
- **THEN** session errors 包含 `admin.email`

#### Scenario: 管理員 email 維持自身值允許
- **GIVEN** 既有商店管理員
- **WHEN** PUT `/shops/{id}` 帶與自身相同的 `admin[email]`
- **THEN** 更新成功、無驗證錯誤

---

### Requirement: 版本變更規則

變更 `grade_id` 時 SHALL 僅允許指向 Active 狀態的版本；維持原 grade（即使該版本已停用）SHALL 允許送出，否則表單將永遠無法儲存。grade 實際變更時系統 SHALL 觸發商店 addon 同步（`ShopAddonSyncService`，同步規則屬 addons 範疇，本 spec 僅規範此交界）。

#### Scenario: 指派停用版本被拒
- **GIVEN** 一個 Inactive 狀態的版本
- **WHEN** PUT `/shops/{id}` 將 grade_id 變更為該版本
- **THEN** session errors 包含 `grade_id`

#### Scenario: 維持原有的停用版本允許
- **GIVEN** 商店現有版本已被停用
- **WHEN** PUT `/shops/{id}` 帶原本的 grade_id
- **THEN** 更新成功、無驗證錯誤

---

### Requirement: 儲存時伺服端重驗認證

認證資料 SHALL 以伺服端為準，表單送來的 `admin[company_name]` MUST NOT 被直接信任寫入。儲存時依 `admin[business_number]` 分流：未帶該欄位 → 兩欄皆不異動；為空值 → `business_number` 與 `company_name` 一併存 NULL；與 DB 現值相同 → `company_name` 取 DB 現值、不發認證 API；有變更 → 伺服端重新呼叫認證 API，失敗 SHALL 拋 `ValidationException`（errors 含 `admin.business_number`，訊息「統一編號認證失敗，請重新進行認證」），成功 SHALL 以 **API 回傳的公司名稱** 寫入。

#### Scenario: 認證後儲存寫入 DB
- **GIVEN** 認證 API 回傳成功（Http fake）
- **WHEN** PUT `/shops/{id}` 帶新的 `admin[business_number]` 與 `admin[company_name]`
- **THEN** shops_admin 的 business_number 與 company_name 已寫入

#### Scenario: 統編變更時重驗並採用 API 公司名稱
- **GIVEN** 認證 API 回傳公司名稱「API 正確公司」（Http fake）
- **WHEN** PUT `/shops/{id}` 變更統編且表單夾帶偽造的 `admin[company_name]`
- **THEN** shops_admin 的 company_name 為「API 正確公司」而非表單值

#### Scenario: 統編重驗失敗被拒
- **GIVEN** 認證 API 回傳空結果（Http fake）
- **WHEN** PUT `/shops/{id}` 變更統編
- **THEN** session errors 包含 `admin.business_number`
- **AND** shops_admin 的統編維持原值

#### Scenario: 統編未變更不發認證 API
- **GIVEN** 既有已認證的商店管理員
- **WHEN** PUT `/shops/{id}` 帶與 DB 相同的統編
- **THEN** 未發出任何 HTTP 請求
- **AND** company_name 維持 DB 現值

---

### Requirement: 統一編號認證端點

`POST /shops/{id}/certify`（需 `Shop.update` 權限，fetch JSON 請求）SHALL 以 `ShopCertifyRequest` 驗證 `business_number` 必填且為 8 位數字，不合法回 422。通過後 SHALL 呼叫政府商工登記 API 查詢：查得 SHALL 回 JSON `{"success": true, "company_name": "..."}`；查無資料 SHALL 回 `{"success": false}`。找不到商店 SHALL 回 404 與 JSON `{"success": false, "message": "找不到該商店"}`。此端點僅供前端預覽公司名稱；實際寫入以儲存時的伺服端重驗為準。

#### Scenario: 缺少統一編號回 422
- **WHEN** POST `/shops/{id}/certify` 不帶 business_number
- **THEN** 系統回應 422 驗證錯誤

#### Scenario: 統一編號格式錯誤回 422
- **WHEN** POST `/shops/{id}/certify` 帶非 8 位數字（含字母或位數不符）
- **THEN** 系統回應 422 驗證錯誤

#### Scenario: 認證成功回傳公司名稱
- **GIVEN** 政府 API 回傳該統編的公司資料（Http fake）
- **WHEN** POST `/shops/{id}/certify` 帶合法統編
- **THEN** 系統回應 JSON `{"success": true, "company_name": "..."}`

#### Scenario: 查無資料回傳失敗
- **GIVEN** 政府 API 回傳空陣列（Http fake）
- **WHEN** POST `/shops/{id}/certify` 帶合法統編
- **THEN** 系統回應 JSON `{"success": false}`

#### Scenario: 認證不存在的商店
- **WHEN** POST `/shops/99999/certify` 帶合法統編
- **THEN** 系統回應 404 與 JSON `{"success": false, "message": "找不到該商店"}`

---

### Requirement: 找不到 id 的處理（刻意設計）

路由 SHALL 採手動 `{id}` 查詢（`ShopService::findShopById()` 經 `ShopRepository::getById()`），MUST NOT 使用 route model binding——找不到時以應用語意回應而非空白 404 頁（源自 spec/7 的設計決策）：edit 與 update SHALL 302 導回 `/shops` 並帶 error flash「找不到該商店」；certify（fetch JSON 請求）SHALL 回 404 JSON（見統一編號認證端點）。

#### Scenario: 編輯不存在的商店
- **WHEN** GET `/shops/99999/edit`
- **THEN** 系統回應 302 導向 `/shops` 並帶 error flash「找不到該商店」

#### Scenario: 更新不存在的商店
- **WHEN** PUT `/shops/99999`
- **THEN** 系統回應 302 導向 `/shops` 並帶 error flash「找不到該商店」

---

### Requirement: 無新增與刪除（刻意設計）

本功能僅支援列表（R）與編輯（U）。系統 MUST NOT 提供商店的新增（create/store）與刪除（destroy）路由——商店帳號由商店端系統開立，後台僅管理既有商店。`sales_id` 與 `expired_at` MUST NOT 在本 UI 編輯（屬 bills 範疇）。

#### Scenario: 無新增路由
- **WHEN** POST `/shops`
- **THEN** 系統回應 405（Method Not Allowed）

#### Scenario: 無刪除路由
- **WHEN** DELETE `/shops/{id}`
- **THEN** 系統回應 405（Method Not Allowed）

---

### Requirement: 已知限制——政府 API 與無對外連線環境

統編認證 SHALL 呼叫政府商工登記 API（`http://data.gcis.nat.gov.tw/od/data/api/5F64D864-61CB-4D0D-8AD9-492047CC1EA6`，timeout 10 秒），此為全站唯一的外部 HTTP 呼叫。呼叫 SHALL 以 try/catch 包裹，任何連線失敗 SHALL 回 `{"success": false}`、MUST NOT 拋出 500。**已知限制**：正式環境無對外網際網路連線，certify 與儲存時重驗在正式環境必回失敗、認證資料無法於正式環境經此流程寫入（2026-06-12 裁示：不改程式碼、僅記載；功能於可連外的開發/測試環境正常運作）。

#### Scenario: 連線失敗回傳失敗而非 500
- **GIVEN** 對政府 API 的連線拋出 ConnectionException（Http fake）
- **WHEN** POST `/shops/{id}/certify` 帶合法統編
- **THEN** 系統回應 JSON `{"success": false}`
