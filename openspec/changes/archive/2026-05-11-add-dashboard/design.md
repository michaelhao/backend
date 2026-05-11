## Context

本專案後台採用 Controller → Service → Repository 分層（見 [CLAUDE.md](CLAUDE.md) 與 archived [add-conference-management](openspec/changes/archive/2026-04-26-add-conference-management/) 慣例）。`layouts/admin.blade.php` 是所有 admin 頁面共用版型。`shops` 表已有 `sales_id`（負責業務）與 `expired_at`（datetime）兩個本次需要的欄位（見 [Shop model](app/Models/Shop.php)）。Claude Design 出的設計稿是 React + inline style 的視覺 mock，本次以 Blade + Tailwind utility class 還原視覺，**不**引入 React/Alpine 等新前端套件。

本次 change 經 `/grill-me` 壓力測試後縮 scope：sidebar 全站改色 與 全站 `APP_TIMEZONE` 切換 拆為獨立 change（`refresh-admin-sidebar` / `change-app-timezone-to-taipei`），本 change 僅處理 dashboard 自身。

## Goals / Non-Goals

**Goals**
- 把 `Dashboard.index` 從寫死 stub 換成以登入者 `sales_id` 為視角的「今日待辦」三區塊
- 第一眼看到內容：前兩個區塊預設展開、第三個收起
- 商店 / conference row 可點進編輯頁（受 `Shop.update` / `Conference.update` 權限 gate）
- 對齊既有慣例：DI、`#[RequiresPermission]`、Tailwind utility、`vendor/bin/pint` 通過
- 透過 Feature test 鎖住 owner 隔離、半年範圍、empty state、權限 gate 等行為

**Non-Goals**
- 任何 schema / migration / seeder 變動（除「刪除 Dashboard.detail permission」一項）
- 引入 Alpine.js / React / 其他前端套件
- 圖表、趨勢、帳務數字、自訂時間區間切換器
- 前台、API、通知、排程
- sidebar 配色變更（屬 `refresh-admin-sidebar`）
- 全站 `APP_TIMEZONE` 切換（屬 `change-app-timezone-to-taipei`）

## Decisions

### 1. Conference 不過濾 user，但 UI 標題明示「全公司」
**選擇**：今日說明會清單顯示所有 `started_at` 落在今日整天的 conferences，**不依**登入者過濾；UI 標題使用「**全公司**今日說明會」明確化。

**替代案**：在 `conferences` 表新增 `sales_id` 欄位以支援「我負責的說明會」。

**理由**：避免本次需求動 schema。設計稿原意是「我負責的說明會」，但 conference 量不大、且當前產品上沒有「業務 ↔ 說明會」的擁有關係模型。若未來需要，再開獨立 change 加 `conferences.sales_id` 與相應 form / 權限。

**約束**：UI 標題**不得**使用第一人稱（避免「我的今日說明會」這類誤導）。三個面板的人稱必須一致：商店面板用「我」第一人稱、conference 面板用「全公司」明示。

### 2. 半年內到期定義
**選擇**：`expired_at BETWEEN now() AND now()->addMonths(6)`（含端點）。

**理由**：直觀、用 Carbon `addMonths(6)` 即可表達；不採「180 天」是因為各月份天數不一，業務語境下「半年」對應月份比較自然。已過期（`expired_at < now()`）**不**列入此區塊。

### 3. 到期色階規則放在 service 層
**選擇**：service 計算每筆 `days_to_expire` 與對應色碼後丟給 view；view 直接用 `style="color: {{ $row['color'] }}"` 渲染。

**替代案**：view 內以 Blade `@if` 判斷天數選 class。

**理由**：色階是業務規則（≤60 紅、≤90 橘、其他黃），歸屬 service。view 只負責渲染。色碼用 hex（沿用設計稿 `#ef4444` / `#f97316` / `#ca8a04`）而非 Tailwind class，避免 Tailwind v4 在 inline `style` 場景下的 purge 雷區。

### 4. 展開/收起互動：原生 `<details><summary>`
**選擇**：採用原生 `<details><summary>` 元素，瀏覽器內建展開行為。Tailwind 樣式以 `[open]` 屬性選擇器控制 chevron 旋轉。

**替代案**：vanilla JS `onclick` toggle hidden class、或引入 Alpine.js / Stimulus。

**理由**：本專案目前無 Alpine（檢查 [layouts/admin.blade.php](resources/views/layouts/admin.blade.php) 與 [resources/js](resources/js/) 無 import）。`<details>` 零 JS 零依賴，CSS transition 雖不能完全自訂但目前需求不需要。**不**保留「擇一最簡」的開放選項，避免 implementer 自行判斷不一致。

### 5. 面板預設展開規則
**選擇**：今日新增商店 與 全公司今日說明會 SHALL 加 `<details open>` 預設展開；即將到期商店 SHALL 不加 `open` 預設收起。

**理由**：proposal 的核心定位是「業務每天打開系統第一眼看到的今日待辦」。前兩塊是「今日的事」、量小（一天 0~3 場 conferences、新增商店通常 0~5 間），第一眼直接看到內容才符合定位。「即將到期」可能 0~30 筆，全展開會把頁面撐很長且不是「今日要做」，預設收起合理。stat badge 仍保留作為總覽錨點。

### 6. 「今日」邊界以 `Asia/Taipei` 計算
**選擇**：`DashboardRepository` 內部明確以 `Asia/Taipei` 計算當日邊界：

```php
$todayStart = Carbon::now('Asia/Taipei')->startOfDay();
$todayEnd   = Carbon::now('Asia/Taipei')->endOfDay();
```

**替代案**：直接用 `today()` / `now()`（依 `config('app.timezone')`，目前為 UTC）。

**理由**：當前 `APP_TIMEZONE` 為 UTC，業務人員在台北每天 00:00 ~ 08:00 新增的資料會落在「UTC 昨日」、dashboard 看不到，違反「業務第一眼看到」的定位。獨立的 `change-app-timezone-to-taipei` change 將處理全站切換；在那之前 dashboard 自己 scoped 修對即可。

**TODO**：`change-app-timezone-to-taipei` 落地後，本 scoped 寫法可改為 `today()` / `now()`，並移除 timezone 參數。

### 7. 剩餘天數計算公式
**選擇**：

```php
$days = (int) Carbon::today('Asia/Taipei')
    ->diffInDays(Carbon::parse($expiredAt->toDateString()));
```

**替代案**：`$expiredAt->diffInDays(now())`（直接帶時分秒）。

**理由**：兩端皆 startOfDay 化（透過 `today()` + `toDateString`），確保結果為穩定整數，避免：
- 同一天內午前/午後查詢結果不同
- Carbon v2（int abs）vs v3（signed float）的語意差異
- 邊界 scenario（恰好 60 / 90 天）測試 flaky

順序為 `today->diffInDays(expiredAt)`：Carbon v3 `diffInDays` 為 signed（`$a->diffInDays($b)` ≈ `$b − $a`），方向反了會得到負值。

### 8. Row 跳轉與權限 gate
**選擇**：商店 row（今日新增、即將到期）→ `route('shops.edit', $row['id'])`，需要 `Shop.update` 權限；conference row → `route('conferences.edit', $row['id'])`，需要 `Conference.update` 權限。無對應權限時 row 為純 `<span>`。

**理由**：dashboard 的 todo 價值要能落到「點進去處理」，否則只是通知頁。權限 gate 是必要：viewer 角色雖然能看 dashboard，但點進去的編輯頁本來就會被 CheckPermission middleware 擋；先在 dashboard 層面隱藏 link 比較不會讓使用者感到「點了沒反應」。沿用既有 [grades/index.blade.php:44](resources/views/admin/grades/index.blade.php#L44) 的 `auth()->user()->hasPermissionTo()` Blade 慣例，無新依賴。

### 9. 不新增 permission key，並一併刪除 `Dashboard.detail`
**選擇**：沿用既有 `Dashboard.index`（[PermissionSeeder.php:15-20](database/seeders/PermissionSeeder.php#L15-L20)）。同時從 PermissionSeeder 移除無 route 對應的 `Dashboard.detail`。

**替代案**：保留 `Dashboard.detail`、留待另一輪清理。

**理由**：本次是首頁覽資料，權限粒度與既有一致即可。`Dashboard.detail` 雖已存在於 seeder 但目前無 route 對應（grep 確認）；既有 `PermissionSeeder::syncPermissions` 邏輯（[PermissionSeeder.php:106-110](database/seeders/PermissionSeeder.php#L106-L110)）會在重跑時 detach role 並 delete，不需要寫 migration。

## Risks / Trade-offs

- **[風險]** `Shop::admin` 為 HasOne 但無 FK 強制，理論上可能為 null（目前 Shop 無 create UI、皆從 seeder/factory 灌入）。**緩解**：service 層用 `optional($shop->admin)->name`，view 端 null 顯示 `—`，並寫一條 feature 測試覆蓋。
- **[風險]** 「今日新增負責商店」逐筆讀 `Shop::admin` 會 N+1。**緩解**：Repository SHALL 以 `->with('admin')` eager-load，查詢一次即可取齊所有聯絡人。
- **[風險]** 移除 `Dashboard.detail` 為 destructive，非預期下重跑 seeder 會把任何使用該 key 的角色關聯一併移除。**緩解**：此 key 目前無 route 對應、不可能有實際授權需求；部署時的 seeder 重跑屬正常流程；rollback 只需 revert seeder 變更後再跑一次 seeder（會 updateOrCreate 回來）。
- **[Trade-off]** 色碼用 hex 而非 Tailwind class → CSS 一致性略降；換來 Tailwind purge 風險為零。專案現已混用兩種寫法，可接受。
- **[Trade-off]** `<details>` 元素的展開動畫不如 React mock 平滑（無 cubic-bezier transition）。**緩解**：保留可日後加 CSS transition 的空間；不引 JS 套件這層約束更重要。
- **[Trade-off]** Dashboard 內部 scoped `Asia/Taipei` 是「兩個 change 並行」的代價：在 timezone change 落地之前，這段 scoped 寫法看起來有點冗餘；落地後即可清理。

## Migration Plan

無 schema / migration 變動。Seeder 變動為 destructive（刪除 `Dashboard.detail`）。以下為 deploy 後驗證順序：

1. 部署後跑 `php artisan db:seed --class=PermissionSeeder`（會自動移除 Dashboard.detail）
2. 立即可在 `/` 看到新版 dashboard
3. 視覺驗證：以擁有 / 不擁有 `Shop.update` 權限的兩個角色各登入一次，確認商店 row 為 `<a>` / `<span>`
4. Rollback：revert PR + 重跑 seeder 把 Dashboard.detail 加回，無資料影響

## 已知事項

- **`Shop::sales()` 關聯** 已存在於 [app/Models/Shop.php:32](app/Models/Shop.php#L32)，直接 `where('sales_id', auth()->id())`。
- **`Shop::admin()` 關聯** 取 `ShopAdmin`（[app/Models/Shop.php:37](app/Models/Shop.php#L37)），HasOne 無 FK 強制，**可能為 null**；service 用 `optional()->name`、view null 顯示 `—`。
- **`Shop.expired_at`** 已 cast 為 `datetime`，`Carbon::diffInDays` 可直接用（搭配 `toDateString()`）。
- **`shops.address`、`conferences.location/attendees`、`conferences.sales_id`** 不存在；本次省略對應 UI 欄位。
- **CheckPermission middleware** 對未授權使用者 `redirect` 到該 role 的 `default_route`（[CheckPermission middleware](app/Http/Middleware/CheckPermission.php)），不直接回 403；測試斷言請用 `assertRedirect()`，沿用 [archived conference-management 的測試慣例](openspec/changes/archive/2026-04-26-add-conference-management/design.md)。`default_route` 與當前 permission 相同時 middleware fall-through 至 `abort(403)`，**無**無窮迴圈風險。
- **設計稿 `daysColor`** 規則：`d ≤ 60 → #ef4444`、`d ≤ 90 → #f97316`、`else → #ca8a04`，原樣搬到 service。
- **Blade view 風格** 參考 sibling [admin/conferences/index.blade.php](resources/views/admin/conferences/index.blade.php) 的 `@extends` / `@section('page-title')` / `@section('content')` 結構。
- **權限檢查的 Blade 慣例** 沿用 [grades/index.blade.php:44](resources/views/admin/grades/index.blade.php#L44) 的 `@if(auth()->user()->hasPermissionTo('Foo.update'))`。
