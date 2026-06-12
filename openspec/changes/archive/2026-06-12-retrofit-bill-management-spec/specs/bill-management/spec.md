# bill-management Specification (delta)

## ADDED Requirements

### Requirement: 帳單列表

系統 SHALL 在 `GET /bills`（需 `Bill.index` 權限）顯示帳單分頁列表，依建立時間新到舊排列、每頁 20 筆，並 eager load `shop` 與 `shopSales`。每筆 SHALL 含帳單編號（點擊開啟明細 Modal）、商店名稱、負責業務、總金額、付款方式、狀態 badge（中文 label 與 CSS class 由 `BillPaymentStatus::label()` / `badgeClass()` 提供）與建立時間；銷帳按鈕 SHALL 僅於 pending / unpaid 顯示，編輯帳務按鈕常駐顯示。

#### Scenario: 有權限者可見帳單列表
- **GIVEN** 持有 `Bill.index` 權限的 Admin 使用者
- **WHEN** GET `/bills`
- **THEN** 系統回應 200 並渲染帳單列表

#### Scenario: Viewer 具列表權限可見帳單列表
- **GIVEN** 持有 Viewer 角色（含 `Bill.index`）的使用者
- **WHEN** GET `/bills`
- **THEN** 系統回應 200 並渲染帳單列表

---

### Requirement: 權限控管

帳務路由 SHALL 受 `auth` middleware 與 `RequiresPermission` attribute 保護：列表 / 明細 / 報價單 / shop-search / shop-info 需 `Bill.index`、建立與試算需 `Bill.create`、編輯帳務需 `Bill.pay`、銷帳需 `Bill.writeoff`。未登入或無對應權限者 SHALL 被導離，且資料不得被變更。

#### Scenario: 未登入者被導向登入頁
- **WHEN** 訪客 GET `/bills`
- **THEN** 系統回應 302 導向 `/login`

#### Scenario: 無 create 權限者不可見建立頁
- **GIVEN** 僅持有 Viewer 角色權限的使用者
- **WHEN** GET `/bills/create`
- **THEN** 系統回應 302 導離

#### Scenario: 無 writeoff 權限者不可銷帳
- **GIVEN** 僅持有 Viewer 角色權限的使用者與一張待審核帳單
- **WHEN** POST `/bills/{id}/writeoff`
- **THEN** 系統回應 302 導離
- **AND** 明細的 `is_effective` 維持 1

---

### Requirement: 列表搜尋篩選

列表 SHALL 支援四個篩選條件且彼此交集：`no` 對帳單編號模糊比對（`LIKE %v%`）；`payment_method`、`payment_status`、`sales_id`（對 `shop_sales_id`）精準比對；留空 = 全部。篩選值 SHALL 於表單與分頁連結間保留。

#### Scenario: 帳單編號模糊比對
- **GIVEN** 編號分別含 `2026A` 與 `2026B` 片段的兩張帳單
- **WHEN** GET `/bills?no=2026A`
- **THEN** 列表僅含編號相符的帳單

#### Scenario: 付款狀態精準篩選
- **GIVEN** 一張待審核與一張已付款的帳單
- **WHEN** GET `/bills?payment_status=1`
- **THEN** 列表僅含待審核帳單

#### Scenario: 付款方式精準篩選
- **GIVEN** 付款方式分別為信用卡與轉帳的兩張帳單
- **WHEN** GET `/bills?payment_method=1`
- **THEN** 列表僅含信用卡帳單

#### Scenario: 負責業務精準篩選
- **GIVEN** 負責業務不同的兩張帳單
- **WHEN** GET `/bills?sales_id={userId}`
- **THEN** 列表僅含該業務的帳單

#### Scenario: 分頁連結保留篩選條件
- **GIVEN** 超過一頁的待審核帳單
- **WHEN** GET `/bills?payment_status=1`
- **THEN** 分頁連結帶有 `payment_status=1`

---

### Requirement: 建立帳單與伺服端重算

`POST /bills`（需 `Bill.create` 權限）SHALL 在單一資料庫交易內建立 `bills`（payment_status = pending）與 `bills_details`，並寫入建立的 status log（from_status = null）。金額 SHALL 一律伺服端重算：明細只接受 identifiers 與排程輸入（type / grade_id / addon_id / quantity / start_at / total_months / payment_type / memo），單價取自 DB 當前定價、項目名稱快照自 DB，前端送來的金額與名稱 MUST NOT 被信任。`shop_sales_id` SHALL 於建立時快照自 `shops.sales_id`。`details.*.start_at` SHALL 不允許過去日期、`total_months` 介於 0–36。

#### Scenario: 明細名稱以 DB 為準
- **GIVEN** 名稱為「進階版」的版本
- **WHEN** POST `/bills` 的明細夾帶偽造的 name 欄位
- **THEN** `bills_details.name` 為「進階版」而非前端值

#### Scenario: 停用版本不可購買
- **GIVEN** 一個 Inactive 狀態的版本
- **WHEN** POST `/bills` 明細指向該版本
- **THEN** 系統回應 422，errors 包含 `details.0.grade_id`

---

### Requirement: Addon 數量計價

type=3（加購功能）明細的 `total_price` SHALL 為「單份期間金額 × quantity」，其中單份期間金額依月份補齊規則以 addon 單價計算；版本類明細 quantity 恆為 1。

#### Scenario: quantity 3 的 addon 總價為三倍
- **GIVEN** 月費 100 的 addon
- **WHEN** POST `/bills` 帶 quantity=3、自當月 1 號起購買 2 個月的該 addon 明細
- **THEN** `bills_details.total_price` 為 600
- **AND** `bills.total_addons` 為 600

---

### Requirement: 同帳單內不可重複加購相同功能

同一張帳單的明細中，同一 `addon_id` MUST NOT 出現於兩列以上（後端驗證，重複時對應 index 回 422）——同 addon 兩列會在安裝時互蓋 `shops_addons` 記錄。不同 addon 各一列 SHALL 允許。

#### Scenario: 重複 addon 被拒
- **WHEN** POST `/bills` 帶兩列相同 addon_id 的明細
- **THEN** 系統回應 422，errors 包含 `details.1.addon_id`

#### Scenario: 兩個不同 addon 允許
- **WHEN** POST `/bills` 帶兩列不同 addon_id 的明細
- **THEN** 建立成功，帳單含兩筆 addon 明細

---

### Requirement: 版本變更的開始日限制

升級（新版本 weight 高於現行）的開始日 SHALL 最早為今日；續約與降級的開始日 SHALL 不得早於 `shops.expired_at + 1 天`（後端於 `StoreBillRequest` 驗證；前端同步鎖定日期選擇器）。

#### Scenario: 降級開始日早於到期日次日被拒
- **GIVEN** 合約尚未到期的商店
- **WHEN** POST `/bills` 帶開始日早於 `expired_at + 1 天` 的降級明細
- **THEN** 系統回應 422，errors 包含 `details.0.start_at`

#### Scenario: 升級今日生效允許
- **GIVEN** 合約尚未到期的商店與更高 weight 的版本
- **WHEN** POST `/bills` 帶今日開始的升級明細
- **THEN** 建立成功

---

### Requirement: 折抵

折抵 SHALL 以 `bills_details` type=4 記錄：名稱快照自 `bills_discount.name`、金額以正數儲存於 `unit_price` / `total_price`、`payment_type` / `start_at` / `expired_at` / `total_months` 留 null。`discount_amount` MUST NOT 大於小計（後端驗證）；`discount_id` 必須存在於 `bills_discount` 且與金額成對出現。

#### Scenario: 折抵明細的期間欄位為 null
- **WHEN** POST `/bills` 帶合法折抵（discount_id + discount_amount）
- **THEN** type=4 明細的 start_at / expired_at / total_months 皆為 null
- **AND** `bills.discount_amount` 等於折抵金額、`bills.total` 已扣除折抵

---

### Requirement: 金額計算規則

明細金額 SHALL 依月份補齊規則計算：`total_months = 0` 時為開始日到當月月底（到期日 = 開始月最後一天 23:59:59）；開始日為當月 1 號時該月算第 1 個月、無 partial（`unit_price × total_months`）；開始日非 1 號時補足本月剩餘天數再加完整月數（`partial_amount + unit_price × total_months`）。部分月份公式 SHALL 為 `round(monthly_price / days_in_start_month × remaining_days)`，其中 `days_in_start_month` 取開始月實際天數（閏年 2 月 = 29，實作必須使用 `Carbon::daysInMonth`）、`remaining_days` 含開始日。每筆明細 SHALL 各自四捨五入至整數；`bills.total` 為有效非折抵明細直接累加再扣折抵，不對總額二次捨入。

#### Scenario: 月底選項的到期日
- **WHEN** 以 2026-04-02、total_months=0 計算
- **THEN** 到期日為 2026-04-30 23:59:59

#### Scenario: 1 號起算 12 個月
- **WHEN** 以 2026-04-01、total_months=12 計算
- **THEN** 到期日為 2027-03-31 23:59:59，金額為 `unit_price × 12`

#### Scenario: 月中起算的 partial month
- **WHEN** 以月費 3000、2026-04-17、total_months=12 計算
- **THEN** partial 為 `round(3000 / 30 × 14) = 1400`，到期日為 2027-04-30 23:59:59

#### Scenario: 閏年二月以 29 天計
- **WHEN** 以 2028-02-15 計算 partial
- **THEN** 分母為 29 天

---

### Requirement: 升級補差額

`start_at` 日期等於 `shops.expired_at` 日期（到期當天立即升級）時 SHALL 以 type=2 計算補差額：`diff_price = 新版本月費 − 現行版本月費`，partial 以 `diff_price` 計、完整月以新版本月費計。`current_grade_price` SHALL 取商店現行版本的定價（List Price），MUST NOT 考慮原始合約的實際成交折扣。`start_at` 晚於 `expired_at` 時補差額不存在，SHALL 直接以新版本價格按 type=1 規則計算。

#### Scenario: 補差額公式
- **GIVEN** 現行版本月費 1000、新版本月費 3000
- **WHEN** 以到期日當天為開始日、total_months=12 計算升級補差額
- **THEN** 金額為 `round(2000 / 當月天數 × 剩餘天數) + 3000 × 12`

---

### Requirement: AJAX 商店搜尋

`GET /bills/shop-search`（需 `Bill.index` 權限）SHALL 驗證 `keyword` 必填（空值回 422）：純數字 SHALL 對商店 ID 完全匹配、其餘對商店名稱模糊比對，結果上限 10 筆，每筆含 `{id} — {name}` 格式的 label。系統 MUST NOT 宣稱或實作「商店代碼」搜尋——shops 表沒有 code 欄位。

#### Scenario: 關鍵字必填
- **WHEN** GET `/bills/shop-search`（不帶 keyword）
- **THEN** 系統回應 422

#### Scenario: 數字關鍵字精準匹配 ID
- **GIVEN** ID 不同的多家商店
- **WHEN** GET `/bills/shop-search?keyword={id}`
- **THEN** 結果僅含該 ID 的商店

#### Scenario: 文字關鍵字模糊匹配名稱
- **GIVEN** 名稱含「旗艦」與不含的兩家商店
- **WHEN** GET `/bills/shop-search?keyword=旗艦`
- **THEN** 結果僅含名稱相符的商店

#### Scenario: 結果上限 10 筆
- **GIVEN** 名稱相符的 12 家商店
- **WHEN** GET `/bills/shop-search?keyword=...`
- **THEN** 結果為 10 筆

---

### Requirement: AJAX 商店資訊

`GET /bills/shop-info?shop_id=`（需 `Bill.index` 權限）SHALL 驗證商店存在且 `sales_id` 非 null（任一不符回 422 與對應訊息），通過後回傳商店基本資訊（含版本與到期日）、該商店 pending / unpaid 帳單數（供前端警示）、grades 清單（依 weight 排序，含 price / weight）、addons 清單（依名稱排序，含 price / type）與商店已啟用的 addons（含到期日）。

#### Scenario: 商店不存在回 422
- **WHEN** GET `/bills/shop-info?shop_id=999999`
- **THEN** 系統回應 422，errors 包含 `shop_id`

#### Scenario: 商店未設業務回 422
- **GIVEN** `sales_id` 為 null 的商店
- **WHEN** GET `/bills/shop-info?shop_id={id}`
- **THEN** 系統回應 422，訊息為「此商店尚未設定負責業務，無法建立帳單」

#### Scenario: 回傳待處理帳單數
- **GIVEN** 有一張待審核帳單的商店
- **WHEN** GET `/bills/shop-info?shop_id={id}`
- **THEN** 回應含 `pending_bill_count = 1` 與 grades / addons / shop_addons 清單

---

### Requirement: AJAX 金額試算

`GET /bills/calculate`（需 `Bill.create` 權限）SHALL 驗證 `unit_price`（整數）、`start_at`（日期）、`total_months`（0–36）必填，依計算規則回傳 `total_price` 與 `expired_at`；type=2 且帶 `current_grade_price` 時改走升級補差額公式。此端點僅供前端預覽顯示，實際入帳金額以建立帳單時的伺服端重算為準。

#### Scenario: 缺少必填欄位回 422
- **WHEN** GET `/bills/calculate`（不帶參數）
- **THEN** 系統回應 422

#### Scenario: 回傳試算金額與到期日
- **WHEN** GET `/bills/calculate?unit_price=3000&start_at=2026-07-01&total_months=12`
- **THEN** 回應 `total_price = 36000`、`expired_at = 2027-06-30 23:59:59` 格式的結果

---

### Requirement: 帳單明細 Modal

`GET /bills/{id}/detail`（需 `Bill.index` 權限）SHALL 回傳 JSON：bill 區塊含編號、商店名、建立人（無建立人顯示「—」）、狀態值與中文 label 及 badge class、金額欄位（total_grade / total_addons / discount_amount / total）、paid_at、invoice_no；details 區塊含全部明細（有效與作廢），每筆含類型值與中文 label、數量、單價、總價、起訖日與 `is_effective`。JSON 組裝 SHALL 由 Service 層完成，Controller 僅調用與回應。找不到帳單 SHALL 回 404 JSON。

#### Scenario: 回傳含有效與作廢明細的 JSON
- **GIVEN** 一張含有效與作廢明細的帳單
- **WHEN** GET `/bills/{id}/detail`
- **THEN** 系統回應 200，JSON 含 bill 基本資訊與全部明細
- **AND** 狀態與類型帶中文 label

#### Scenario: 帳單不存在回 404
- **WHEN** GET `/bills/999999/detail`
- **THEN** 系統回應 404 JSON

---

### Requirement: 匯出報價單

`GET /bills/{id}/quotation`（需 `Bill.index` 權限）SHALL 回傳 PDF 附件下載，檔名為 `{Y-m-d}_{商店名}_{帳單id}_報價單.pdf`（商店名中不安全字元以 `_` 取代）。內容 SHALL 僅含 `is_effective = 1` 的明細（作廢項目不匯出）；折抵列不顯示起訖日、金額不加負號；折抵金額僅 `discount_amount > 0` 時顯示。PDF 以 `barryvdh/laravel-dompdf` 產生、中文字型採 WQY MicroHei。找不到帳單 SHALL 回 404。

#### Scenario: 下載 PDF 附件
- **GIVEN** 一張既有帳單
- **WHEN** GET `/bills/{id}/quotation`
- **THEN** 系統回應 200，Content-Type 為 `application/pdf` 且 Content-Disposition 為附件下載

#### Scenario: 作廢明細不出現在報價單資料
- **GIVEN** 一張含作廢明細的帳單
- **WHEN** 組裝報價單資料
- **THEN** 輸出僅含有效明細

#### Scenario: 帳單不存在回 404
- **WHEN** GET `/bills/999999/quotation`
- **THEN** 系統回應 404

---

### Requirement: 編輯帳務與狀態流轉

`PATCH /bills/{id}`（需 `Bill.pay` 權限，AJAX JSON）SHALL 更新 `payment_status` / `paid_at` / `invoice_no`，狀態有變更時寫入 `bills_status_logs`（from / to / operator）。狀態流轉 SHALL 受限：paid 帳單 MUST NOT 變更為其他狀態（回 422「已付款的帳單無法變更狀態」）；invalid 為終態，MUST NOT 轉回任何其他狀態（回 422「已失效的帳單無法變更狀態」）。找不到帳單 SHALL 回 404 JSON。

#### Scenario: 已付款帳單不可降轉
- **GIVEN** 一張已付款帳單
- **WHEN** PATCH `/bills/{id}` 將狀態改為待付款
- **THEN** 系統回應 422，errors 包含 `payment_status`

#### Scenario: 已失效帳單不可轉為已付款
- **GIVEN** 一張已失效帳單
- **WHEN** PATCH `/bills/{id}` 將狀態改為已付款
- **THEN** 系統回應 422
- **AND** 帳單狀態維持已失效

#### Scenario: 已失效帳單不可轉回任何狀態
- **GIVEN** 一張已失效帳單
- **WHEN** PATCH `/bills/{id}` 分別將狀態改為待審核與待付款
- **THEN** 系統皆回應 422，帳單狀態維持已失效

---

### Requirement: 付款安裝流程

狀態由非 paid 轉為 paid 時，系統 SHALL 於同一交易內逐筆處理有效非折抵明細：`start_at` 日期 **小於等於今日** SHALL 立即安裝；大於今日 SHALL 寫入 `bills_future_effect`（execute_at 取 start_at 日期部分）等排程觸發。安裝（installDetail）SHALL 冪等——`applied_at` 已有值即跳過；更新 shops 前 SHALL `lockForUpdate()` 鎖定商店列，防止排程與手動付款並發互蓋。版本類安裝 SHALL 更新 `shops.grade_id` / `shops.expired_at` 並依新版本的 `grades_addons` 同步商店 addon（`ShopAddonSyncService` 交界）；加購類安裝 SHALL upsert `shops_addons`（source=Purchased、status=Enabled），`AddonType::Quota` 並同時寫入 `shop_addon_balances`（quantity、expired_at）。安裝完成 SHALL 寫入 `applied_at`。

#### Scenario: 轉已付款即安裝今日明細
- **GIVEN** 一張待付款帳單，含今日生效的版本明細
- **WHEN** PATCH `/bills/{id}` 轉為已付款
- **THEN** `shops.grade_id` 與 `expired_at` 已更新，明細 `applied_at` 已寫入

#### Scenario: 過去 start_at 明細立即安裝
- **GIVEN** 一張待付款帳單，含 start_at 為昨日的明細
- **WHEN** PATCH `/bills/{id}` 轉為已付款
- **THEN** 明細立即安裝（`applied_at` 非 null）
- **AND** 不產生 `bills_future_effect` 記錄

#### Scenario: 未來明細寫入排程表
- **GIVEN** 一張待付款帳單，含 start_at 為未來日期的明細
- **WHEN** PATCH `/bills/{id}` 轉為已付款
- **THEN** 產生 `bills_future_effect` 記錄（finished_at = null），明細未安裝

#### Scenario: 安裝具冪等性
- **GIVEN** 一筆 `applied_at` 已有值的明細
- **WHEN** 再次對其執行 installDetail
- **THEN** 商店狀態不變、不重複安裝

---

### Requirement: 付款並發鎖

轉為 paid 前系統 SHALL 取得 `Cache::lock("bill_pay_{帳單id}", 10)`；取不到鎖 SHALL 由 Service 層拋出 `BillPaymentLockedException`（domain exception，自帶 render 對應 429 JSON「付款處理中，請勿重複操作」），Service 層 MUST NOT 直接以 `abort()` 觸碰 HTTP 層。鎖 SHALL 於處理完成後釋放。

#### Scenario: 持鎖期間重複付款回 429
- **GIVEN** 他人已持有該帳單的付款鎖
- **WHEN** PATCH `/bills/{id}` 轉為已付款
- **THEN** 系統回應 429 JSON
- **AND** 帳單狀態未變更

---

### Requirement: 銷帳

`POST /bills/{id}/writeoff`（需 `Bill.writeoff` 權限）SHALL 僅允許 pending / unpaid 帳單（否則回 422「只有待審核或待付款的帳單可以銷帳」）；`detail_ids` 必填且每筆 SHALL 屬於該帳單（含他帳單明細回 422「部分明細不屬於此帳單」）。銷帳 SHALL 於同一交易內：將勾選明細 `is_effective` 設 0、寫入 `canceled_at` 與 `canceled_by`；重算 bills 三個金額欄位（僅有效非折抵明細）；重算後小計低於折抵金額時 SHALL 將 `discount_amount` 與折抵明細列同步縮減至等於小計；全部明細皆作廢時 SHALL 將帳單轉為 invalid 並寫入 status log（operator 記錄操作人）。

#### Scenario: 銷帳標記明細作廢
- **GIVEN** 一張待審核帳單與其中一筆明細
- **WHEN** POST `/bills/{id}/writeoff` 勾選該明細
- **THEN** 明細 `is_effective = 0`、`canceled_at` 與 `canceled_by` 已寫入
- **AND** 帳單金額已重算

#### Scenario: 折抵自動縮減
- **GIVEN** 折抵 500、銷帳後小計僅 300 的帳單
- **WHEN** 銷帳使小計低於折抵
- **THEN** `discount_amount` 縮減為 300，折抵明細列同步更新

#### Scenario: 全部作廢轉為已失效
- **GIVEN** 僅一筆有效明細的待審核帳單
- **WHEN** POST `/bills/{id}/writeoff` 勾選全部明細
- **THEN** 帳單轉為 invalid
- **AND** `bills_status_logs` 寫入該轉換與操作人

#### Scenario: 他帳單明細被拒
- **WHEN** POST `/bills/{id}/writeoff` 夾帶他帳單的明細 id
- **THEN** 系統回應 422，errors 包含 `detail_ids`

---

### Requirement: 未來生效排程

`bills_future_effect` SHALL 為未來生效項目的唯一真相來源，由 Artisan 指令 `bills:process-future-effects` 每日 00:05 輪詢：撈取 `execute_at <= today AND finished_at IS NULL`、依 `execute_at ASC, id ASC` 排序（確保同商店同日多筆依建立順序執行），逐筆呼叫 installDetail（各自包在交易內）後寫入 `finished_at`；單筆失敗 SHALL Log error 後跳過、不中斷其他筆，留待次日重試。指令 SHALL 可手動補跑（冪等保護由 `applied_at` 提供）。**架構假設**：單一 Worker 執行；多伺服器部署須改為 Queue Job 搭配 SKIP LOCKED。

#### Scenario: 到期的未來生效項目被安裝
- **GIVEN** 一筆 execute_at 為今日、未完成的 future effect
- **WHEN** 執行 `bills:process-future-effects`
- **THEN** 對應明細已安裝、`finished_at` 已寫入

#### Scenario: 單筆失敗不中斷其他筆
- **GIVEN** 兩筆到期 future effect，其一指向不存在的商店
- **WHEN** 執行 `bills:process-future-effects`
- **THEN** 壞資料筆失敗留待重試（finished_at 維持 null）
- **AND** 另一筆正常安裝完成

---

### Requirement: 帳單編號產生（刻意設計）

帳單編號 SHALL 為 `b{Ymd}{His}{8 位亂數}` 格式並由 DB UNIQUE index 保證唯一。產生方式 SHALL 為一次產生 3 個候選、以單一查詢排除已存在者後取第一個可用值；3 個候選全部碰撞時拋出例外、由 DB unique 約束兜底（與舊 spec「碰撞時重試 3 次」字面不同，為等價的既有實作，照實記載）。

#### Scenario: 建立帳單產生合規編號
- **WHEN** POST `/bills` 成功建立帳單
- **THEN** `bills.no` 符合 `b{14 位日期時間}{8 位數字}` 格式且全表唯一

---

### Requirement: 無獨立付款端點（刻意設計）

付款 SHALL 一律經由編輯帳務 Modal `PATCH /bills/{id}`（`Bill.pay` 權限）將狀態設為 paid 觸發；系統 MUST NOT 提供獨立的 `POST /bills/{id}/pay` 端點（舊 spec 殘留的該路由字樣為筆誤）。

#### Scenario: 無 pay 路由
- **WHEN** POST `/bills/{id}/pay`
- **THEN** 系統回應 404（路由不存在）

---

### Requirement: 版本內含 addon 的購買限制由前端把關（刻意設計）

「版本已包含的 addon 標記為不可購買」SHALL 由前端建立頁把關，後端 MUST NOT 重複驗證——誤買不破壞資料完整性（安裝為 upsert，僅多收費可人工銷帳），與「同帳單重複 addon」的後端強制驗證刻意區分。

#### Scenario: 後端不擋版本內含 addon
- **GIVEN** 商店現行版本已包含某 addon
- **WHEN** POST `/bills` 加購該 addon
- **THEN** 建立成功（由前端 UI 阻止此情境）

---

### Requirement: 保留欄位與已知限制（刻意設計）

`payment_method` 於建單流程 SHALL 必填（in:1,2,3），DB 欄位保留 nullable（歷史資料相容）。`bills_status_logs.reason` 欄位 SHALL 保留於 schema，現行無任何寫入路徑（預留人工備註用途）。`bills_status_logs.operator_id` 為 null 時 SHALL 代表系統排程自動觸發。本功能 MUST NOT 發出任何對外 HTTP 呼叫（正式環境無對外網際網路連線）。

#### Scenario: 建單缺 payment_method 被拒
- **WHEN** POST `/bills` 不帶 payment_method
- **THEN** 系統回應 422，errors 包含 `payment_method`
