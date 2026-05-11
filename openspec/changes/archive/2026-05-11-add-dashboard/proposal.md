## Why

現有 `/`（`Dashboard.index`）使用的 view [resources/views/admin/dashboard.blade.php](resources/views/admin/dashboard.blade.php) 是寫死的假資料 stub（文章數、留言數、瀏覽量等本系統不存在的領域），對使用者沒有實際價值。Claude Design 已產出一份「店務管理儀表板」設計稿（方案 B 淺色版），把儀表板重新定位為「業務人員每天打開系統第一眼看到的今日待辦」，包含：

1. 我（登入者）今日新增的負責商店
2. 全公司今日的說明會清單
3. 我負責商店中半年內即將到期的（紅/橘/黃 警示）

每個區塊可展開/收起；前兩個區塊預設展開，第三個區塊預設收起。

## What Changes

- 重寫 `Dashboard.index` 行為：
    - 「今日新增負責商店」：以登入者 `sales_id` 過濾
    - 「全公司今日說明會」：**不**以使用者過濾（UI 標題明示「全公司」）
    - 「半年內到期負責商店」：以登入者 `sales_id` 過濾
- 新增 `DashboardRepository` 與 `DashboardService`，沿用既有 Repository → Service → Controller 分層。
- 重寫 [resources/views/admin/dashboard.blade.php](resources/views/admin/dashboard.blade.php)，以 Tailwind utility class 還原設計稿淺色版視覺：greeting / 三個 stat badge / 三個可展開面板 / empty state。展開機制採原生 `<details><summary>`（不引入任何 JS 套件）。
- 商店 row（今日新增 / 即將到期）在使用者具備 `Shop.update` 權限時為 link（連 `shops.edit`），否則純文字；說明會 row 同理綁 `Conference.update`。
- 「今日」邊界以 `Asia/Taipei` 計算（DashboardRepository 內部明確指定）。剩餘天數以日期粒度 `Carbon::parse($expiredAt->toDateString())->diffInDays(today('Asia/Taipei'))` 計算，避免時分秒漂移與 Carbon 版本語意差異。
- 移除 PermissionSeeder 中無 route 對應的 `Dashboard.detail` 項；seeder 重跑會自動 detach + delete。
- 既有 `Dashboard.index` 權限保留，**不**新增其他 permission key。
- 既有路由 `Route::get('/', [DashboardController::class, 'index'])->name('dashboard')` **不**變動。

## Capabilities

### New Capabilities
- `dashboard`: 後台首頁的當日業務待辦總覽（今日新增負責商店、全公司今日說明會、即將到期負責商店）

### Modified Capabilities
<!-- 無。 -->

## 相依與相關 change

- **`change-app-timezone-to-taipei`**（獨立 change）：將全站 `APP_TIMEZONE` 由 `UTC` 改為 `Asia/Taipei`。本 change **不** block 在其之上：DashboardRepository 自己以 `Asia/Taipei` scoped 計算邊界；timezone change 落地後可移除這層 scoped 寫法（在 design.md 標 TODO）。
- **`refresh-admin-sidebar`**（獨立 change）：sidebar 全站從深色改為淺色紫品牌。本 change **不**包含 sidebar 視覺改動，避免單一 PR 同時動跨 9 頁的視覺與 dashboard 邏輯。

## Impact

- **資料庫**：**無 schema 變更**。沿用 `shops.sales_id`、`shops.expired_at`（[shops_table migration](database/migrations/2026_04_14_000001_create_shops_table.php)、[alter_shops_add_sales_id_and_datetime_expired_at](database/migrations/2026_04_21_000001_alter_shops_add_sales_id_and_datetime_expired_at.php)）、`conferences.started_at`。
- **程式碼**：新增 `app/Repositories/DashboardRepository.php`、`app/Services/DashboardService.php`；修改 `app/Http/Controllers/DashboardController.php`、`resources/views/admin/dashboard.blade.php`、`database/seeders/PermissionSeeder.php`；新增 `tests/Feature/DashboardTest.php`。
- **路由**：無新增。
- **權限**：無新增；移除 `Dashboard.detail`（destructive，部署需重跑 seeder）。
- **視覺**：僅 dashboard 頁面（layouts sidebar 不在此 change）。
- **非影響範圍（明確排除，後續若需再開新 change）**：
    - shops 加 address 欄位、conferences 加 location/attendees 欄位、conferences 加負責人 `sales_id`
    - 圖表 / 趨勢 / KPI 卡片 / 帳務數字
    - 自訂時間區間切換器（今日 / 本月 / 自訂）
    - 引入 Alpine.js 或其他前端套件
    - 全站 sidebar 配色（屬 `refresh-admin-sidebar`）
    - 全站 `APP_TIMEZONE` 切換（屬 `change-app-timezone-to-taipei`）
