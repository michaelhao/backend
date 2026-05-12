# dashboard Specification

## Purpose
TBD - created by archiving change add-dashboard. Update Purpose after archive.
## Requirements
### Requirement: 儀表板存取權限

系統 SHALL 以既有 `Dashboard.index` 權限保護 `GET /` 路由。未登入使用者 SHALL 被導向 `/login`。已登入但無 `Dashboard.index` 權限的使用者 SHALL 被導向其角色 `default_route`（沿用 `CheckPermission` middleware 行為）。

#### Scenario: 未登入存取
- **WHEN** 訪客 GET `/`
- **THEN** 系統回應 302 導向 `/login`

#### Scenario: 授權使用者存取
- **WHEN** 擁有 `Dashboard.index` 權限的使用者 GET `/`
- **THEN** 系統回應 200 並渲染 `admin.dashboard` view

#### Scenario: 已登入但無 Dashboard 權限
- **WHEN** 登入但無 `Dashboard.index` 權限的使用者 GET `/`
- **THEN** 系統回應 302 導向其角色 `default_route`

---

### Requirement: 今日新增負責商店清單

系統 SHALL 提供「當日新增商店」資料區塊，僅顯示登入者為負責業務（`shops.sales_id = auth()->id()`）且 `created_at` 落在當日（依 `Asia/Taipei` 時區，從 00:00:00 到 23:59:59）的商店。每筆 SHALL 包含 `id`（給 row 跳轉用）、`name`、`created_at` 時間（HH:mm，格式化為 `Asia/Taipei`）、聯絡人姓名（取自 `Shop::admin()->name`，若 admin 為 null 則為 `null`）。資料 SHALL 依 `created_at` 升冪排序。view 層 SHALL 將 null 聯絡人渲染為 `—`（U+2014 em-dash）。

#### Scenario: 只看到自己負責的商店
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 登入者 A 與另一業務 B
- **AND** 今日新增 2 筆商店（`sales_id = A`）與 1 筆商店（`sales_id = B`）
- **WHEN** A GET `/`
- **THEN** 「當日新增商店」區塊出現 2 筆，且皆為 A 負責的商店

#### Scenario: 非當日新增的商店不出現
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 登入者 A 負責的商店：1 筆 `created_at = 2026-05-09 09:00:00 +08:00` 與 1 筆 `created_at = 2026-05-08 23:00:00 +08:00`
- **WHEN** A GET `/`
- **THEN** 「當日新增商店」區塊只有 1 筆（2026-05-09 那筆）

#### Scenario: 「今日」邊界以 Asia/Taipei 計算
- **GIVEN** Carbon::setTestNow('2026-05-09 02:00:00', 'Asia/Taipei')（= UTC 2026-05-08 18:00:00）
- **AND** 登入者 A 在 Taipei 2026-05-09 01:00 新增 1 筆商店
- **WHEN** A GET `/`
- **THEN** 該筆商店出現於「當日新增商店」區塊

#### Scenario: 渲染聯絡人姓名（admin 存在）
- **GIVEN** 登入者 A 今日新增的商店帶有 `ShopAdmin.name = '陳小明'`
- **WHEN** A GET `/`
- **THEN** 該商店列顯示聯絡人「陳小明」

#### Scenario: 聯絡人為 null 時顯示 em-dash
- **GIVEN** 登入者 A 今日新增的商店無對應 `ShopAdmin` 記錄
- **WHEN** A GET `/`
- **THEN** 該商店列聯絡人欄位渲染為 `—`（U+2014 em-dash）
- **AND** 不發生例外或錯誤

---

### Requirement: 全公司今日說明會清單

系統 SHALL 提供「**全公司**今日說明會」資料區塊，顯示 `conferences.started_at` 落在當日整天範圍（`Asia/Taipei`）的所有說明會，**不**依登入者過濾。每筆 SHALL 包含 `id`（給 row 跳轉用）、`name` 與 `started_at`–`ended_at` 的時間區間字串（HH:mm–HH:mm，格式化為 `Asia/Taipei`）。資料 SHALL 依 `started_at` 升冪排序。UI 標題 MUST 使用「**全公司**今日說明會」字面，**不**得使用第一人稱（如「我的今日說明會」）。

#### Scenario: 不依使用者過濾
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 今日有 2 場說明會
- **WHEN** 任一授權使用者 GET `/`
- **THEN** 「全公司今日說明會」區塊出現 2 筆

#### Scenario: 非今日的說明會不出現
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 1 場 `started_at = 2026-05-09 14:00 +08:00`、1 場 `started_at = 2026-05-10 14:00 +08:00`、1 場 `started_at = 2026-05-08 14:00 +08:00` 的說明會
- **WHEN** 授權使用者 GET `/`
- **THEN** 「全公司今日說明會」區塊只有 1 筆（2026-05-09 那場）

---

### Requirement: 即將到期負責商店清單

系統 SHALL 提供「即將到期商店（半年內）」資料區塊，僅顯示登入者為負責業務（`shops.sales_id = auth()->id()`）且 `expired_at` 落在 `[Carbon::now('Asia/Taipei'), Carbon::now('Asia/Taipei')->addMonths(6)]` 的商店（含端點）。每筆 SHALL 包含 `id`（給 row 跳轉用）、`name`、`expired_at`（YYYY-MM-DD，以 `Asia/Taipei` 格式化）、剩餘天數（依「剩餘天數計算」Requirement）、依天數計算的色碼。資料 SHALL 依 `expired_at` 升冪排序。

#### Scenario: 只看到自己負責的即將到期商店
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 登入者 A 與另一業務 B 各有 1 筆 `expired_at = 2026-06-08 12:00:00 +08:00`（30 天後）商店
- **WHEN** A GET `/`
- **THEN** 「即將到期商店」區塊只有 1 筆，為 A 負責的

#### Scenario: 已過期商店不列入
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 登入者 A 負責的商店：1 筆 `expired_at = 2026-05-08 12:00:00 +08:00`（昨日，已過期）
- **WHEN** A GET `/`
- **THEN** 「即將到期商店」區塊不含該筆

#### Scenario: 半年外的商店不列入
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 登入者 A 負責的商店：1 筆 `expired_at = 2026-12-31 12:00:00 +08:00`（約 7 個月後）
- **WHEN** A GET `/`
- **THEN** 「即將到期商店」區塊不含該筆

#### Scenario: 半年端點包含
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 登入者 A 負責的商店：1 筆 `expired_at = 2026-11-09 23:59:59 +08:00`（恰好半年）
- **WHEN** A GET `/`
- **THEN** 「即將到期商店」區塊包含該筆

#### Scenario: 今日尚未到時刻仍列入（剩餘天數 0）
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** 登入者 A 負責的商店：1 筆 `expired_at = 2026-05-09 23:00:00 +08:00`（今日晚間到期）
- **WHEN** A GET `/`
- **THEN** 「即將到期商店」區塊包含該筆
- **AND** 該筆剩餘天數為 0、色碼為 `#ef4444`

---

### Requirement: 剩餘天數計算

系統 SHALL 以日期粒度計算「即將到期商店」的剩餘天數：

```php
$days = (int) Carbon::today('Asia/Taipei')
    ->diffInDays(Carbon::parse($expiredAt->toDateString()));
```

公式 MUST 在 service 層執行，回傳整數。兩端皆 startOfDay 化以避免時分秒漂移與 Carbon v2/v3 `diffInDays` 語意差異（v2 回傳 absolute int、v3 回傳 signed float）。順序為 `today->diffInDays(expiredAt)` 才能在 Carbon v3（signed）取得正值（剩餘日數 = 到期日 − 今日）。

#### Scenario: 30 天剩餘
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** `expired_at = 2026-06-08 任意時分秒 +08:00`
- **WHEN** service 計算
- **THEN** `days = 30`

#### Scenario: 60 天邊界
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** `expired_at = 2026-07-08 任意時分秒 +08:00`
- **WHEN** service 計算
- **THEN** `days = 60`

#### Scenario: 90 天邊界
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** `expired_at = 2026-08-07 任意時分秒 +08:00`
- **WHEN** service 計算
- **THEN** `days = 90`

#### Scenario: 0 天（今日到期）
- **GIVEN** Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')
- **AND** `expired_at = 2026-05-09 任意時分秒 +08:00`（今日，含尚未到的時刻）
- **WHEN** service 計算
- **THEN** `days = 0`

---

### Requirement: 到期色階規則

系統 SHALL 對每筆「即將到期商店」的剩餘天數套用以下色階規則：

- 剩餘天數 ≤ 60 → 紅色 `#ef4444`
- 剩餘天數 ≤ 90 → 橘色 `#f97316`
- 其他（91 天以上）→ 黃色 `#ca8a04`

色碼計算 MUST 在 service 層完成，view 直接渲染。

#### Scenario: 30 天為紅色
- **WHEN** 商店剩餘天數為 30
- **THEN** 該筆色碼為 `#ef4444`

#### Scenario: 75 天為橘色
- **WHEN** 商店剩餘天數為 75
- **THEN** 該筆色碼為 `#f97316`

#### Scenario: 150 天為黃色
- **WHEN** 商店剩餘天數為 150
- **THEN** 該筆色碼為 `#ca8a04`

#### Scenario: 60 天邊界為紅色
- **WHEN** 商店剩餘天數為 60
- **THEN** 該筆色碼為 `#ef4444`

#### Scenario: 90 天邊界為橘色
- **WHEN** 商店剩餘天數為 90
- **THEN** 該筆色碼為 `#f97316`

---

### Requirement: 三區塊展開/收起互動

每個資料區塊 SHALL 使用原生 HTML `<details><summary>` 元素實作展開/收起，**不**依賴 Alpine.js / Stimulus / vanilla JS toggle。標題列（`<summary>` 內容）含 icon、標題、stat badge（顯示該區塊資料總筆數）、單位、chevron 視覺指示。stat badge 數字 SHALL 等於該區塊 collection 的筆數（包含 0）。

「今日新增負責商店」與「全公司今日說明會」 SHALL 預設展開（`<details open>`）；「即將到期負責商店」 SHALL 預設收起（`<details>`，無 `open` 屬性）。使用者點擊標題列 SHALL 切換該區塊展開/收起狀態（瀏覽器原生行為）。

#### Scenario: 今日新增商店預設展開
- **WHEN** 使用者 GET `/` 後立即檢視 DOM
- **THEN** 「今日新增負責商店」區塊使用 `<details open>` 渲染，內容可見

#### Scenario: 全公司今日說明會預設展開
- **WHEN** 使用者 GET `/` 後立即檢視 DOM
- **THEN** 「全公司今日說明會」區塊使用 `<details open>` 渲染，內容可見

#### Scenario: 即將到期商店預設收起
- **WHEN** 使用者 GET `/` 後立即檢視 DOM
- **THEN** 「即將到期負責商店」區塊使用 `<details>`（無 `open`）渲染，內容預設不可見

#### Scenario: 點擊切換展開/收起（manual / browser 驗證）
- **WHEN** 使用者點擊任一 `<summary>`
- **THEN** 該 `<details>` 元素的 `open` 屬性切換，內容顯示/隱藏
- **NOTE** 此 scenario 屬瀏覽器原生行為，feature test（無 JS engine）不覆蓋；以 manual / browser 驗證

---

### Requirement: Row 詳細頁跳轉與權限 gate

商店 row（今日新增、即將到期）SHALL 在使用者擁有 `Shop.update` 權限時包覆為 `<a href="{{ route('shops.edit', $row['id']) }}">`；無 `Shop.update` 權限時 SHALL 以 `<span>` 渲染（純文字、不可點擊）。

Conference row SHALL 在使用者擁有 `Conference.update` 權限時包覆為 `<a href="{{ route('conferences.edit', $row['id']) }}">`；無 `Conference.update` 權限時 SHALL 以 `<span>` 渲染。

#### Scenario: 商店 row 在有權限時為連結
- **GIVEN** 登入者擁有 `Shop.update` 權限，且今日有負責商店資料
- **WHEN** GET `/`
- **THEN** 商店 row 為 `<a href="/shops/{id}/edit">` 元素

#### Scenario: 商店 row 在無權限時為純文字
- **GIVEN** 登入者擁有 `Dashboard.index` 但**無** `Shop.update` 權限
- **WHEN** GET `/`
- **THEN** 商店 row 為 `<span>` 元素（不含 href）

#### Scenario: Conference row 在有權限時為連結
- **GIVEN** 登入者擁有 `Conference.update` 權限，且今日有說明會
- **WHEN** GET `/`
- **THEN** Conference row 為 `<a href="/conferences/{id}/edit">` 元素

#### Scenario: Conference row 在無權限時為純文字
- **GIVEN** 登入者擁有 `Dashboard.index` 但**無** `Conference.update` 權限
- **WHEN** GET `/`
- **THEN** Conference row 為 `<span>` 元素

---

### Requirement: 各區塊空資料 Empty State

當任一區塊 query 結果為空，view SHALL 顯示對應中文文案，**不**隱藏整個區塊：

- 當日新增商店：「今日無新增負責商店」
- 全公司今日說明會：「今日無說明會」
- 即將到期商店：「暫無半年內到期的負責商店」

對應 stat badge 數字 SHALL 顯示 `0`。

#### Scenario: 今日無新增
- **GIVEN** 登入者 A 今日無新增商店
- **WHEN** A GET `/`
- **THEN** 「當日新增商店」區塊 stat badge 顯示 0
- **AND** 展開內容顯示「今日無新增負責商店」

#### Scenario: 今日無說明會
- **GIVEN** 今日無任何 conferences
- **WHEN** 授權使用者 GET `/`
- **THEN** 「全公司今日說明會」區塊 stat badge 顯示 0
- **AND** 展開內容顯示「今日無說明會」

#### Scenario: 半年內無到期
- **GIVEN** 登入者 A 無任何 `expired_at` 落在半年內的商店
- **WHEN** A GET `/`
- **THEN** 「即將到期商店」區塊 stat badge 顯示 0
- **AND** 展開內容顯示「暫無半年內到期的負責商店」

---

### Requirement: 移除 Dashboard.detail 權限

`PermissionSeeder` 的 `Dashboard` 模組 SHALL **不**再包含 `detail` action。Seeder 重跑時 SHALL 透過既有 `syncPermissions` 邏輯自動 detach 所有引用該 permission 的角色關聯並 delete 該 permission。

#### Scenario: Seeder 重跑後 Dashboard.detail 不存在
- **GIVEN** 資料庫已存在 `Dashboard.detail` permission（來自舊版 seeder）
- **WHEN** 執行 `php artisan db:seed --class=PermissionSeeder`
- **THEN** `Permission::where('name', 'Dashboard.detail')->exists()` 為 false
- **AND** 任何先前綁定該 permission 的 role 不再含此關聯

#### Scenario: Dashboard.index 權限不受影響
- **GIVEN** Admin 角色擁有 `Dashboard.index` permission
- **WHEN** 執行 PermissionSeeder
- **THEN** Admin 角色仍擁有 `Dashboard.index`

