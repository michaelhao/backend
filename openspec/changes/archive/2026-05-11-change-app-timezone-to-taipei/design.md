## Context

Laravel 預設 `'timezone' => 'UTC'`，本專案沿用至今。業務在 Asia/Taipei，產生兩類問題：(1) user-input datetime 欄位有 8 小時偏移、(2) `today()` / `whereDate()` 在台北 00:00 ~ 08:00 之間誤判為「昨日」。

DB 不存 timezone 資訊（MySQL native datetime 即字串時鐘）；Carbon 在解析與比對時依 `config('app.timezone')` 推斷。所以**改 config 不動 DB 資料**，但 datetime 的「解讀」會整個改變。

## Goals / Non-Goals

**Goals**
- 一次性把全站「今日」「現在」語意對齊到 Asia/Taipei
- 修對既有 user-input datetime 欄位的 8h 偏移（`expired_at`、`started_at` 等）
- 不寫 data migration、可一行 config revert 回滾

**Non-Goals**
- 修正歷史 `created_at` / `updated_at` 時間（接受顯示時間「往前 8 小時」的視覺影響）
- 引入 timezone-per-user 偏好（all users 統一 Asia/Taipei）
- 改變 DB 欄位型別、寫 migration

## Decisions

### 1. 不做歷史資料 migration
**選擇**：`config/app.php` 改 timezone，DB datetime 字串值不變。

**替代案**：寫 migration 把 `created_at` / `updated_at` 全部 +8h。

**理由**：
- 影響面更大、rollback 變難（需要反向 migration）
- 系統還在初期、無對外 audit 需求（內部後台）
- 字串字面值不變，但解讀時區從 UTC 變 Asia/Taipei，等同所有 datetime 欄位的 wall-clock 在 deploy 瞬間「往前 8 小時」
- 影響範圍包含 `created_at` / `updated_at`（顯示偏移、無實質影響）以及使用者輸入欄位 `BillDetail.expired_at` / `ShopAddon.expired_at` / `ShopAddonBalance.expired_at` / Conference 系列 `started_at` 等（既有 row 提早 8h 失效）
- 業務以月為單位、合約少 8h 可吸收；新建 row 反而修對既有 8h 偏移 bug

### 2. add-dashboard 不 block 本 change
**選擇**：兩個 change 並行，dashboard 自己 scoped 修對。本 change 落地後 dashboard 清理 scoped 寫法。

**理由**：dashboard 是高頻面板、不該等 timezone 切換。並行的代價是 dashboard 內部多寫一段「`Asia/Taipei` scoped」程式碼，落地後一行刪除。

### 3. Cron 觸發時刻隨 config 一起位移
**選擇**：不顯式 `->timezone()` 鎖定，讓 [routes/console.php:12](routes/console.php#L12) `bills:process-future-effects` 的 `dailyAt('00:05')` 自動從「server UTC 00:05（= Taipei 08:05）」變成 **Taipei 00:05**。

**理由**：Laravel Schedule 預設 timezone 取自 `config('app.timezone')`。位移後業務語意更直觀（凌晨剛過即處理當日 bill，而非延遲到上午八點）。本 change 接受此副作用，不額外鎖定。

### 4. timezone 用 hardcoded 字面值、不走 env
**選擇**：`config/app.php` 直接寫 `'timezone' => 'Asia/Taipei'`，不改成 `env('APP_TIMEZONE', ...)`。

**理由**：本專案為內部後台、無多區部署需求，hardcoded 更難誤改、rollback 也只是改回字面值。`.env` / `.env.example` 不新增 `APP_TIMEZONE` 鍵。

## Risks / Trade-offs

- **[風險]** 既有測試套件可能因時區改變 fail。**緩解**：本 change 的 tasks 包含「跑 full test suite + 修正 fail case」一節；不通過不 ship。
- **[風險]** `created_at` / `updated_at` 顯示時間視覺往前 8 小時，使用者可能困惑。**緩解**：`Carbon` instance 比對與排序語意都不變（字串值未動），只是「字面」往前 8h；可在 deploy notes 預先告知。
- **[風險]** 任何依 `database/migrations` 或 `seeders` 寫死 UTC 時刻的測試 fixture 可能爆。**緩解**：grep 搜「UTC」字面 + tests 跑一遍能抓到。

## Migration Plan

無 schema 變動。一行 config 改完後跑全測試 → 修 fail case → deploy。

Rollback：revert config 一行 + 重新部署。

## 已知事項（在 grill 時詳細盤點）

預期受影響的程式位置（grep 結果）：

- [app/Services/BillPaymentService.php:111](app/Services/BillPaymentService.php#L111) — `today()->toDateString()`
- [app/Repositories/BillFutureEffectRepository.php:24](app/Repositories/BillFutureEffectRepository.php#L24) — `whereDate('execute_at', '<=', today())`
- [app/Console/Commands/ProcessBillFutureEffects.php](app/Console/Commands/ProcessBillFutureEffects.php) — daily job
- [app/Http/Requests/StoreBillRequest.php:35](app/Http/Requests/StoreBillRequest.php#L35) — `'after_or_equal:today'`
- [app/Http/Requests/StoreBillRequest.php:52](app/Http/Requests/StoreBillRequest.php#L52) — `$shop->expired_at->copy()->addDay()->startOfDay()`
- 對應的 feature tests

每處需驗證：時區改為 `Asia/Taipei` 後，行為是否仍符合業務預期、測試是否仍 pass。
