## Why

[config/app.php:68](config/app.php#L68) 目前為 Laravel 預設 `'timezone' => 'UTC'`，但本專案業務全在 Asia/Taipei (UTC+8)。後果：

1. **使用者輸入時間欄位有 8 小時偏移**：使用者在表單填 `2026-12-31 23:59`（心裡是台北時間），Laravel 以 UTC 解讀並存入 DB，實際語意比使用者預期晚 8 小時。
2. **「今日」相關 query 每天有 8 小時黑洞**：使用 `today()` / `whereDate()` 的 service 在台北 00:00 ~ 08:00 之間，會回傳「UTC 昨日」資料，與業務直覺不符。`add-dashboard` change 為此被迫在 DashboardRepository 內部 scoped `Asia/Taipei`。

統一 `APP_TIMEZONE` 為 `Asia/Taipei` 可一次解決上述兩類問題，且與業務區一致。

## What Changes

- 修改 [config/app.php](config/app.php) 將 `'timezone' => 'UTC'` 改為 `'timezone' => 'Asia/Taipei'`。
- 修正既有測試套件中因時區改變而 fail 的 case（含 `BillPaymentService`、`StoreBillRequest` 等用 `today()` / `now()` / `after_or_equal:today` 的測試）。
- `add-dashboard` change 落地後，移除 `DashboardRepository` 內部 `'Asia/Taipei'` 參數，改用 `today()` / `now()`（標 TODO 在 add-dashboard design.md）。
- **不**寫資料 migration：歷史 datetime 欄位字串值不變，但解讀方式改變。

## Impact

- **資料庫**：**無 schema 變更**。歷史資料不 migrate（已知影響：`created_at` / `updated_at` 顯示時間會「往前 8 小時」，視為已知；`expired_at` / `started_at` 等使用者輸入欄位反而修正既有 8h 偏移）。
- **程式碼**：修改 `config/app.php`；測試套件 review。
- **路由 / 權限**：無變動。
- **影響範圍**：所有使用 `Carbon`、`now()`、`today()`、`whereDate()`、`whereBetween` 比對 datetime 的程式（app/ 下 grep 即 12+ 處 service / request / repository / job，加上對應 tests 預估 30+ 處；以 task 2.1 全測跑為準）。
- **Rollback**：revert config 一行即可回滾。

## 相依與相關 change

- 與 `add-dashboard` 並行：dashboard 自己 scoped `Asia/Taipei`，不 block 在本 change 之上。本 change 落地後 dashboard 可移除 scoped 寫法（屬清理性 follow-up）。
- 與 `refresh-admin-sidebar` 完全獨立。
