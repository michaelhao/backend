## 1. 切換 config

- [x] 1.1 修改 [config/app.php](config/app.php) `'timezone' => 'UTC'` → `'timezone' => 'Asia/Taipei'`（hardcoded 字面值，不引入 `env('APP_TIMEZONE', ...)`）

## 2. 既有測試套件回歸

- [x] 2.1 docker 內跑 `docker compose exec backend-api php artisan test --compact` 全套
- [x] 2.2 修正所有因時區語意改變而 fail 的測試 case
    - 預計受影響：`BillPaymentService`、`BillFutureEffectRepository`、`StoreBillRequest`、`ProcessBillFutureEffects`、相對應 feature tests
- [x] 2.3 grep `UTC` 字面與 `Carbon::parse(..., 'UTC')` 等寫死 UTC 的位置，掃描範圍涵蓋 `app/`、`tests/`、`database/migrations/`、`database/seeders/`，逐一檢視是否需改

## 3. add-dashboard 清理（前置條件：`add-dashboard` 已合併）

> 若 `add-dashboard` 尚未合併，§3 全段跳過、留作 follow-up issue 追蹤；不要 block 本 change ship。

- [x] 3.1 確認 `add-dashboard` 已合併（未合則跳過 §3 整段）
- [x] 3.2 修改 `app/Repositories/DashboardRepository.php` 移除 `'Asia/Taipei'` 參數，改用 `today()` / `now()`
- [x] 3.3 修改 `app/Services/DashboardService.php` 中 `daysToExpire` 的 `Carbon::today('Asia/Taipei')` → `Carbon::today()`
- [x] 3.4 跑 `php artisan test --filter=DashboardTest` 全綠（29 tests / 45 assertions）

## 4. 收尾

- [x] 4.1 docker 內跑 `vendor/bin/pint --dirty --format agent`
- [x] 4.2 docker 內跑 `php artisan test --compact` 全綠
- [x] 4.3 執行 `openspec validate change-app-timezone-to-taipei --strict` 通過
- [x] 4.4 部署前準備一段 release note：「自此版本起，全站時區由 UTC 改為 Asia/Taipei；既有 created_at / updated_at 顯示時間將往前 8 小時，但實際資料未變動」
- [ ] 4.5 OpenSpec archive：`openspec archive change-app-timezone-to-taipei`
