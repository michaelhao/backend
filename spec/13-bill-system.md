# 帳務系統

----

## 背景描述

商店購買版本或加購功能時，需要正式的帳務記錄。  
帳單支援版本升降級補差額、加購功能、未來生效等情境，並有完整的付款狀態流程。

----

## 資料庫模型

### `bills`

| 欄位名          | 類型          | 說明                                                              |
| --------------- | ------------- | ----------------------------------------------------------------- |
| id              | int           | Primary Key                                                       |
| no              | varchar(32)   | 帳單編號，格式：`b{Ymd}{His}{8位亂數}`，例：`b2026042016561200105063`；ascii charset，加 UNIQUE index，程式端碰撞時最多重試 3 次 |
| creator_id      | int           | FK → users(id)，帳單建立人                                        |
| shop_id         | int           | FK → shops(id)                                                    |
| shop_sales_id   | int           | FK → users(id)，商店負責業務（建立時快照）                        |
| total           | int unsigned  | 總金額（折抵後）                                                  |
| total_grade     | int unsigned  | 版本項目總金額                                                    |
| total_addons    | int unsigned  | 加購功能總金額                                                    |
| discount_amount | int unsigned  | 折抵金額，nullable                                                |
| payment_status  | tinyint       | 1: pending 待審核, 2: unpaid 待付款, 3: paid 已付款, 4: invalid 已失效 |
| payment_method  | tinyint       | 付款方式，nullable（付款前為 null）：1: 信用卡, 2: 轉帳, 3: 現金 |
| paid_at         | datetime      | 實際付款時間，nullable（付款後寫入，財務對帳用）                  |
| invoice_no      | varchar(100)  | 發票號碼，nullable                                                |
| created_at      | datetime      |                                                                   |
| updated_at      | datetime      |                                                                   |

### `bills_details`

| 欄位名        | 類型          | 說明                                          |
| ------------- | ------------- | --------------------------------------------- |
| id            | int           | Primary Key                                   |
| bill_id       | int           | FK → bills(id)                                |
| type          | tinyint       | 1: grades 版本, 2: upgrade_fee_diff 升級補差額, 3: addons 加購功能, 4: discount 折抵 |
| payment_type  | tinyint       | 1: 月繳, 2: 季繳, 3: 年繳，nullable（type=4 折抵不適用） |
| quantity      | int           | 數量（addons 使用，版本固定為 1）             |
| unit_price    | int           | 單價（有號整數；type=4 以正數儲存，計算時扣除） |
| total_price   | int           | 總金額 = unit_price × quantity（有號整數）     |
| name          | varchar(100)  | 項目名稱（快照）                              |
| start_at      | datetime      | 啟用日（不允許過去時間）                      |
| expired_at    | datetime      | 到期日                                        |
| total_months  | int           | 購買月數（0 = 當月月底；開始日為當月最後一天時不可選 0） |
| is_effective  | tinyint       | 1: 有效, 0: 作廢（銷帳）                      |
| canceled_at   | datetime      | 銷帳時間，nullable                            |
| canceled_by   | int           | FK → users(id)，銷帳操作人，nullable          |
| applied_at    | datetime      | 安裝完成時間，nullable；installDetail 完成後寫入，作為冪等保護 |
| memo          | varchar(255)  | 備註，nullable                                |
| created_at    | datetime      |                                               |
| updated_at    | datetime      |                                               |

### `bills_future_effect`

| 欄位名         | 類型          | 說明                                        |
| -------------- | ------------- | ------------------------------------------- |
| id             | int           | Primary Key                                 |
| bill_id        | int           | FK → bills(id)                              |
| bill_detail_id | int           | FK → bills_details(id)                      |
| execute_at     | date          | 預定執行日（由 bills_details.start_at 轉 date） |
| finished_at    | date          | 完成執行日，nullable                        |
| created_at     | datetime      |                                             |
| updated_at     | datetime      |                                             |

### `bills_discount`

折抵方案的查找表，對應 UI 折抵區塊的「選方案」下拉選項。

| 欄位名      | 類型          | 說明                     |
| ----------- | ------------- | ------------------------ |
| id          | int           | Primary Key              |
| name        | varchar(100)  | 方案名稱（如：優惠券）   |
| description | varchar(255)  | 說明，nullable           |
| created_at  | datetime      |                          |
| updated_at  | datetime      |                          |

折抵細節（套用哪個方案、金額）以 `bills_details` type=4 記錄：
- `name`：`bills_discount.name` 快照
- `unit_price` / `total_price`：折抵金額（正數儲存，計算 total 時扣除）
- `payment_type` / `start_at` / `expired_at` / `total_months`：不適用，留 null

### `bills_status_logs`

記錄 payment_status 的所有變更，提供完整審計追蹤。

| 欄位名      | 類型          | 說明                                                  |
| ----------- | ------------- | ----------------------------------------------------- |
| id          | int           | Primary Key                                           |
| bill_id     | int           | FK → bills(id)                                        |
| from_status | tinyint       | 變更前狀態，nullable（建立時為 null）                 |
| to_status   | tinyint       | 變更後狀態                                            |
| operator_id | int           | FK → users(id)，nullable（null = 系統排程自動觸發）   |
| reason      | varchar(255)  | 備註，nullable                                        |
| created_at  | datetime      |                                                       |

### `shops` 異動

新增欄位：

| 欄位名   | 類型 | 說明                       |
| -------- | ---- | -------------------------- |
| sales_id | int  | FK → users(id)，nullable   |

調整欄位：

- `expired_at`：由 `date` 改為 `datetime`

----

## 帳單項目計算邏輯

### 月份補齊規則

- **total_months = 0（月底）**：到期日為開始月份的最後一天 23:59:59。開始日為當月最後一天時，不可選此選項。
  - 範例：2026-04-02 買 0 個月 → 到期 2026-04-30 23:59:59
- **開始日為當月 1 號**：該月算第 1 個月，共 `total_months` 個月。
  - 範例：2026-04-01 買 12 個月 → 到期 2027-03-31 23:59:59
- **開始日非當月 1 號**：補足本月剩餘天數（partial month）+ `total_months` 個完整月。
  - 範例：2026-04-17 買 12 個月 → 補 Apr 17–30 + 12 個月 → 到期 2027-04-30 23:59:59

### 部分月份金額公式

```
remaining_days = days_in_start_month - start_day + 1   // 含開始日
partial_amount = round( monthly_price / days_in_start_month × remaining_days )
```

- `days_in_start_month`：開始月份的**實際**總天數（閏年 2 月 = 29 天）；實作必須使用 `Carbon::daysInMonth`，禁止寫死
- `remaining_days`：開始日到該月底的天數（**含開始日**）
  - 範例：4 月 17 日 → 30 - 17 + 1 = **14 天**

### 捨入規則

- 每筆 `bills_details.total_price` **各自四捨五入至整數**
- `bills.total = Σ total_price（is_effective=1, type≠4） − discount_amount`，直接累加，不對總額再次四捨五入

### 金額計算規則

| 情境                     | 公式                                                   |
| ------------------------ | ------------------------------------------------------ |
| total_months = 0         | `partial_amount`（開始日到月底）                       |
| 開始日為 1 號            | `unit_price × total_months`（無 partial）              |
| 開始日非 1 號            | `partial_amount + unit_price × total_months`           |

### 升級補差額（type=2 upgrade_fee_diff）

適用情境：`start_at` 日期 = `shop.expired_at` 日期（到期當天立即升級）

```
diff_price         = new_grade_price - current_grade_price
partial_amount     = round( diff_price / days_in_start_month × remaining_days )
full_months_amount = new_grade_price × total_months
total_price        = partial_amount + full_months_amount
```

> `current_grade_price`：商店當前版本（`shop.grade.price`）的月費**定價（List Price）**，不考慮原始合約的實際成交折扣。升級補差額一律以版本定價為準。

#### 非立即升級（`start_at` > `shop.expired_at`）

補差額不存在，直接以新版本價格用 type=1 計算：

```
total_price = calculateDetailTotal(new_grade_price, start_at, total_months)
```

### 版本限制

| 情境 | 開始日限制                      |
| ---- | ------------------------------- |
| 升級 | 最早為當天（today）             |
| 降級 | 最早為 shops.expired_at + 1 天  |
| 展期 | 最早為 shops.expired_at + 1 天  |

> `start_at` 不允許過去時間（任何情境皆適用）。

----

## 建立帳單 UI 流程

單頁步驟式（不換頁），依序展開，字型 Noto Sans TC，白底深色字商務風格。

---

### Step 1：搜尋商店

```
Hi！{登入者姓名}
今天你要幫哪間商店處理帳務呢？

[輸入商店 ID、代碼或名稱關鍵字]  [搜尋]
```

- 支援：商店 ID（完全匹配）、`shops.code`（完全匹配）、商店名稱（模糊搜尋）
- 搜尋結果以下拉選單顯示，最多 10 筆：`{id} — {name}（{code}）`
- 業務選取後點「確認」進入 Step 2

---

### Step 2：Loading

顯示 loading 動畫，背景 AJAX `GET /bills/shop-info?shop_id=` 驗證：
- 商店存在
- `shops.sales_id` 不得為 null（否則顯示錯誤）

載入完成後進入 Step 3。

---

### Step 3：確認商店資訊 ＋ 選擇處理項目

```
Hi！{登入者姓名}
現在要處理的是

| 商店 ID | 商店名稱 | 版本 | 狀態 | 到期日 |
| ------- | -------- | ---- | ---- | ------ |
| ...     | ...      | ...  | ...  | ...    |

> **⚠ 警示**（若商店有 payment_status = pending 或 unpaid 的帳單）：  
> 「此商店有 N 張待處理帳單，建議先完成付款或銷帳後再建立新帳單，以避免升級殘值計算錯誤。」

要處理什麼項目呢？

[button 版本 ✓]  [button Addon ✓]
```

- 兩個按鈕為**獨立切換**（toggle），可同時勾選
- 勾選後按鈕右上角顯示藍色打勾標記，對應設定區塊展開
- 回傳 hidden 資料：grades 清單（weight、price）、shops_addons（addon_id、expired_at，status=1）

---

### Step 4：設定項目

#### 版本設定區塊（選擇版本後展開）

```
[button 升級]  [button 續約]  [button 降級]

[select 版本]
[開始日]
[select 購買月數]
[金額（auto）]
[預計到期日（auto）]
```

**類型按鈕（升級/續約/降級）**
- 根據所選類型 + 商店目前版本，過濾可選版本清單
- 升級：列出 weight > 當前版本的 grades
- 降級：列出 weight < 當前版本的 grades
- 續約：僅顯示當前版本

**開始日**
- 升級：最早為今日
- 續約 / 降級：鎖定為 `shops.expired_at + 1 天`（不可手動修改）

**升級中途警示**

若升級開始日選擇 `start_at < shop.expired_at`（即合約尚未到期），顯示橘色警告：

```
⚠ 注意：所選開始日（{start_at}）早於目前合約到期日（{expired_at}），
  新合約到期日將與原合約不同，請與業務確認後再送出。
```

**購買月數**

| 值 | label |
|----|-------|
| 0  | 月底  |
| 1–5 | N 個月 |
| 6  | 6 個月（半年） |
| 7–11 | N 個月 |
| 12 | 12 個月（年繳） |
| 13–23 | N 個月 |
| 24 | 24 個月（2 年繳） |
| 25–35 | N 個月 |
| 36 | 36 個月（3 年繳） |

- 選項涵蓋 1–36 個月全部，特殊月數加標籤
- 開始日為當月 1 號時，不顯示「0 = 月底」選項
- 開始日為當月最後一天時，不顯示「0 = 月底」選項
- 最大 36

**金額 / 預計到期日**：版本、開始日、月數三欄填齊後自動 AJAX 計算

---

#### Addon 設定區塊（選擇 Addon 後展開）

```
[select Addon]  [開始日]  [select 購買月數]  [金額（auto）]  [預計到期日（auto）]  [✕]
[+ 新增項目]
```

- 可動態新增多列，每列獨立設定
- **版本已包含的 addon**：顯示但標記「已包含」，**不可購買**
- **已購買的 addon**：顯示標記「已購買，到期 {expired_at}」，仍可再購
- 同一張帳單內已選過的 addon 在其他列標示「已加入」，不可重複選取
- `AddonType::Quota` 顯示數量（quantity）欄位，必填數字
- 月數選項與標籤同版本設定

---

#### 折抵區塊

```
[select 折抵方案]  [input 折抵金額]
```

折抵方案選項（來自 `bills_discount` 表）：
- 帳戶餘額
- 優惠券
- 行銷活動
- 人工調整折抵

- 選方案後才可輸入金額（純數字）
- 折抵金額不得大於小計
- 套用後顯示橘色提示列確認內容

---

#### 帳單明細（Order Summary）

版本或 Addon 任一有資料時自動顯示於底部。

| 欄位 | 說明 |
|------|------|
| 版本 / 名稱 | 項目名稱 |
| 開始 → 到期 | start_at ～ expired_at |
| 金額 | total_price |

- 小計（subtotal）
- 折抵金額（若有，顯示 −NT$xxx）
- **合計**（不低於 0）

---

### 送出

表單 `POST /bills` → 建立 `bills`（payment_status = pending）+ `bills_details`。

後端驗證：`discount_amount ≤ subtotal`。

----

## 帳單列表（`GET /bills`）

### 搜尋條件

| 欄位 | 類型 | 說明 |
|------|------|------|
| no | text | 帳單編號，模糊搜尋 |
| shop_sales_id | select | 負責業務，選項從 users 撈取；留空 = 全部 |
| payment_method | select | 付款方式：1: 信用卡, 2: 轉帳, 3: 現金；留空 = 全部 |
| payment_status | select | 狀態：1: pending, 2: unpaid, 3: paid, 4: invalid；留空 = 全部 |

搜尋列在表格上方，GET 參數傳遞，支援分頁保留條件。

### 列表欄位

| 欄位 | 說明 |
|------|------|
| 帳單編號（no） | 點擊開啟「帳單明細 Modal」 |
| 商店名稱 | |
| 負責業務 | shop_sales_id → user.name |
| 總金額（total） | |
| 付款方式 | |
| 狀態 badge | pending / unpaid / paid / invalid |
| 建立時間 | |
| 操作 | 銷帳按鈕（pending / unpaid 才顯示）、編輯帳務按鈕（常駐顯示） |

----

## 帳單明細 Modal

點擊帳單編號後，AJAX `GET /bills/{id}/detail` 取得資料，開啟 modal 顯示帳單明細。

### Modal 內容

```
帳單編號：{bill.no}
商店：{shop.name}
建立人：{creator.name}
狀態：{payment_status badge}
────────────────────────────────────────────
 項目名稱  類型  總價  起始日  到期日
 ...（逐筆有效 bills_details，is_effective = 1）
────────────────────────────────────────────
小計：{total_grade + total_addons}
折抵：{discount_amount}
總金額：{total}

── 作廢項目（僅有作廢項目時才顯示此區塊）────────
 項目名稱  類型  總價  起始日  到期日
 ...（is_effective = 0，名稱以刪除線標示，文字灰色）
```

後端 `GET /bills/{id}/detail` 回傳 JSON，包含 bill 基本資訊與 bills_details 列表（含有效與作廢）。

Modal 底部有「匯出報價單」按鈕，點擊觸發 `GET /bills/{id}/quotation` 下載 PDF。

----

## 匯出報價單

`GET /bills/{id}/quotation` — 需登入、具備 `Bill.index` 權限。

回傳 PDF 附件下載，檔名格式：`{Y-m-d}_{shops.name}_{bills.id}_報價單.pdf`

### PDF 內容

```
報價單

帳單編號：{bill.no}
商店：{bill.shop.name}
日期：{今日日期}
────────────────────────────────────────────
 項目名稱  類型  起始日  到期日  總價
 ...（僅 is_effective = 1 的項目；折抵類型不顯示起始/到期日）
────────────────────────────────────────────
                        小計  NT$xxx
                        折抵  NT$xxx   ← 僅 discount_amount > 0 時顯示
                      總金額  NT$xxx
```

- 作廢項目（is_effective = 0）不匯出
- 折抵金額欄位不加負號（標題已表達含義）
- 使用 `barryvdh/laravel-dompdf` 產生，字型採 WQY MicroHei（storage/fonts/wqy-microhei.ttf）

----

## 編輯帳務 Modal

點擊「編輯帳務」按鈕，開啟 modal，AJAX `PATCH /bills/{id}` 儲存。

### Modal 內容

```
付款狀態  [select: pending / unpaid / paid / invalid]
付款日期  [date picker，nullable]
發票號碼  [text input，nullable]
          [取消]  [儲存]
```

### 後端處理（`PATCH /bills/{id}`）

1. 更新 `bills.payment_status`、`bills.paid_at`、`bills.invoice_no`
2. 寫入 `bills_status_logs`（若 payment_status 有變更）
3. **若 payment_status 由非 paid 變更為 paid(3)**：觸發付款安裝流程（同「帳務付款」章節）

> 付款安裝流程仍使用 `Cache::lock("bill_pay_{$bill->id}", 10)` 防止重複執行。

----

## 銷帳（帳務項目作廢）

**僅限 payment_status = pending(1) 或 unpaid(2) 的帳單可執行銷帳。已付款（paid）項目需走退款流程。**

帳單列表提供「銷帳」按鈕（僅 pending / unpaid 顯示），點擊後開啟 modal。

```
{bill.no}
─────────────────────────────────────────
[☐] 項目名稱  類型  總價  起始日  到期日  狀態
[☐] 項目名稱  類型  總價  起始日  到期日  狀態
─────────────────────────────────────────
[取消]  [進行銷帳]
```

AJAX `POST /bills/{id}/writeoff`，送出勾選的 `detail_ids`。

後端處理：
1. 驗證 payment_status IN [pending, unpaid]，否則回傳錯誤
2. 將勾選的 `bills_details.is_effective` 設為 0，同時寫入 `canceled_at = now()`、`canceled_by = 當前登入 user id`
3. 重新計算 `bills.total`、`bills.total_grade`、`bills.total_addons`（僅計算 is_effective=1、type≠4 的項目）
4. 銷帳後若 `subtotal（重算後）< bills.discount_amount`，自動將 `bills.discount_amount` 縮減至等於 `subtotal`（防止折抵大於小計）
5. 若所有 details 均已作廢：
   - 將 `bills.payment_status` 設為 4（invalid）
   - 寫入 `bills_status_logs`

----

## 帳務付款

付款透過「編輯帳務 Modal」將 payment_status 設為 paid(3) 觸發，不再提供獨立的 pay 端點。

後端先取得 `Cache::lock("bill_pay_{$bill->id}", 10)`，取不到鎖則回傳 429，防止重複付款。

後端處理：
1. `bills.payment_status` → 3（paid）、寫入 `bills.paid_at`（若有填寫）
2. 寫入 `bills_status_logs`
3. 逐一處理 `bills_details`（is_effective=1，type≠4）：
   - `start_at` 日期 = 今日 → 立即安裝
   - `start_at` 日期 > 今日 → 寫入 `bills_future_effect`，等排程觸發

安裝邏輯（installDetail）：

> 執行前檢查 `bills_details.applied_at IS NOT NULL`，若已有值則直接跳過（冪等保護）。
> 更新 `shops` 前須以 `lockForUpdate()` 鎖定商店記錄，防止排程與手動付款並發時互蓋 `expired_at`。

**版本 / 升級補差額（type 1 or 2）**
- 比對 `grades.weight` 判斷升降級
- 更新 `shops.grade_id`、`shops.expired_at`
- 升降級時依 `grades_addons` diff 同步 `shops_addons`（呼叫 `ShopAddonSyncService::syncForShop`）

**加購功能（type 3）**
- `shops_addons` updateOrCreate（source=Purchased）
- 若為 `AddonType::Quota`，同時新增 `shop_addon_balances` 記錄（quantity、expired_at）

安裝完成後寫入 `bills_details.applied_at = now()`。

所有異動以 `DB::transaction` 包裹。

----

## 帳務未來生效

`bills_future_effect` 是未來生效項目的唯一真相來源。  
採用每日排程 Artisan Command 輪詢觸發。

### 寫入時機

帳務付款（`POST /bills/{id}/pay`）時，`bills_details.start_at` 日期 > 今日的項目寫入此表：

| 欄位           | 值                                          |
| -------------- | ------------------------------------------- |
| bill_id        | bills_details.bill_id                       |
| bill_detail_id | bills_details.id                            |
| execute_at     | bills_details.start_at 轉 date（取日期部分） |
| finished_at    | null                                        |

### 排程指令

**指令**：`php artisan bills:process-future-effects`

**檔案**：`app/Console/Commands/ProcessBillFutureEffects.php`

**排程**（`routes/console.php`）：
```php
Schedule::command('bills:process-future-effects')->dailyAt('00:05');
```

### 執行邏輯

```
query bills_future_effect
  WHERE execute_at <= today
    AND finished_at IS NULL
  ORDER BY execute_at ASC, id ASC
↓
foreach record:
  呼叫 BillPaymentService::installDetail(BillDetail)
    → 內部已有 applied_at 冪等保護，重跑不會重複安裝
  成功 → finished_at = today
  失敗 → Log::error(...)，跳過，留待次日重試
```

- 排序 `execute_at ASC, id ASC`：確保同一商店同日多筆依建立順序執行，避免版本狀態衝突（如先升級再展期）
- 每筆各自包在 `DB::transaction`，單筆失敗不影響其他筆
- 可手動補跑：`docker compose exec backend-api php artisan bills:process-future-effects`
- **架構假設**：此指令設計為單一 Worker 執行；若未來需多伺服器部署，應改用 Queue Job 搭配 `SKIP LOCKED` 機制

----

## 權限（Permissions）

| Permission     | 說明                                     |
| -------------- | ---------------------------------------- |
| Bill.index     | 帳單列表                                 |
| Bill.create    | 建立帳單                                 |
| Bill.pay       | 帳務付款                                 |
| Bill.writeoff  | 銷帳（部分明細作廢）                     |
| Bill.void      | 作廢整張帳單（與 writeoff 語義不同）     |

----

## Seeder 調整

**ShopSeeder**
- 補上 `sales_id`（指向已存在的 user id）
- `expired_at` 改為 datetime 格式（`Y-m-d H:i:s`）
