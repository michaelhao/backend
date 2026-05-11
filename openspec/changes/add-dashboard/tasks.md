## 1. Repository

- [x] 1.1 建立 `app/Repositories/DashboardRepository.php`，含三個 method（內部以 `Asia/Taipei` 計算當日邊界）：
    ```php
    use Carbon\Carbon;
    
    private function todayRange(): array
    {
        return [
            Carbon::now('Asia/Taipei')->startOfDay(),
            Carbon::now('Asia/Taipei')->endOfDay(),
        ];
    }
    
    public function getMyNewShopsToday(int $userId): Collection
    {
        [$start, $end] = $this->todayRange();
        return Shop::query()->with('admin')
            ->where('sales_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')->get();
    }
    
    public function getTodayConferences(): Collection
    {
        [$start, $end] = $this->todayRange();
        return Conference::query()
            ->whereBetween('started_at', [$start, $end])
            ->orderBy('started_at')->get();
    }
    
    public function getMyExpiringShops(int $userId): Collection
    {
        $now = Carbon::now('Asia/Taipei');
        return Shop::query()
            ->where('sales_id', $userId)
            ->whereBetween('expired_at', [$now, $now->copy()->addMonths(6)])
            ->orderBy('expired_at')->get();
    }
    ```
- [x] 1.2 風格與既有 [ShopRepository](app/Repositories/ShopRepository.php) / [ConferenceRepository](app/Repositories/ConferenceRepository.php) 一致：query builder chain、無業務邏輯。
- [x] 1.3 註記 TODO：`change-app-timezone-to-taipei` 落地後可改用 `today()` / `now()`，移除 `'Asia/Taipei'` 參數。

## 2. Service

- [x] 2.1 建立 `app/Services/DashboardService.php`，注入 `DashboardRepository`：
    ```php
    public function getOverview(int $userId, User $user): array
    {
        return [
            'new_shops' => $this->mapNewShops($this->repo->getMyNewShopsToday($userId)),
            'today_conferences' => $this->mapConferences($this->repo->getTodayConferences()),
            'expiring_shops' => $this->mapExpiringShops($this->repo->getMyExpiringShops($userId)),
            'can_edit_shop' => $user->hasPermissionTo('Shop.update'),
            'can_edit_conference' => $user->hasPermissionTo('Conference.update'),
        ];
    }
    ```
- [x] 2.2 DTO 轉換規則：
    - `new_shops` 每筆：`['id', 'name', 'created_at_hm' (format 'H:i' in Asia/Taipei), 'contact' (optional($shop->admin)->name)]`
    - `today_conferences` 每筆：`['id', 'name', 'time_range' (format 'H:i' . '–' . 'H:i' in Asia/Taipei)]`
    - `expiring_shops` 每筆：`['id', 'name', 'expired_at' (Y-m-d), 'days' (int), 'color' (hex)]`
- [x] 2.3 私有 `daysToExpire(Carbon $expiredAt): int`（以 spec 為準，today→diffInDays(expiredAt)）
- [x] 2.4 私有 `daysColor(int $d): string`：`$d <= 60 ? '#ef4444' : ($d <= 90 ? '#f97316' : '#ca8a04')`。
- [x] 2.5 風格參考 [ShopService::getIndexData()](app/Services/ShopService.php#L27)：array shape PHPDoc 註明，DI 走 constructor property promotion。

## 3. Controller

- [x] 3.1 修改 [app/Http/Controllers/DashboardController.php](app/Http/Controllers/DashboardController.php)：
    - 注入 `DashboardService`
    - `index()` 維持 `#[RequiresPermission('Dashboard.index')]`
    - 呼叫 `$service->getOverview(auth()->id(), auth()->user())` 並把結果連同今日日期字串、使用者名稱傳給 view
    - 今日日期字串：`Carbon::today('Asia/Taipei')->isoFormat('Y年M月D日（dd）')`（取得「2026年5月9日（六）」格式）

## 4. View（重寫 dashboard）

- [x] 4.1 重寫 [resources/views/admin/dashboard.blade.php](resources/views/admin/dashboard.blade.php)：
    - greeting 區：日期字串、`嗨，歡迎回來，{{ Auth::user()->name }}！👋`、副標「以下是您今日的重要資訊總覽」
    - 三個 stat badge（橘 `#fff7ed` / 綠 `#f0fdf4` / 紫 `#faf5ff`），數字大字粗體，label 小字
    - 三個 `<details>` 面板：
        - 「今日新增負責商店」`<details open>` ✅ 預設展開
        - 「全公司今日說明會」`<details open>` ✅ 預設展開（標題字面寫死「全公司」，**不**得用「我的」）
        - 「即將到期負責商店」`<details>` ❌ 預設收起
        - `<summary>` 內含 icon、title、count、單位、chevron（用 `[open]` 屬性 + Tailwind transform 控制旋轉）
    - row 模板（商店）：
        ```blade
        @if($overview['can_edit_shop'])
            <a href="{{ route('shops.edit', $row['id']) }}" class="...">{{ $row['name'] }}</a>
        @else
            <span class="...">{{ $row['name'] }}</span>
        @endif
        ```
    - row 模板（conference）類似，gate 改用 `$overview['can_edit_conference']` + `route('conferences.edit', ...)`
    - contact 顯示：`{{ $row['contact'] ?? '—' }}`
    - Empty state 文案：「今日無新增負責商店」/「今日無說明會」/「暫無半年內到期的負責商店」
    - 整體背景沿用 `bg-gray-100`（既有 `<body>`）

## 5. PermissionSeeder 清理

- [x] 5.1 從 [database/seeders/PermissionSeeder.php](database/seeders/PermissionSeeder.php) 的 `$modules['Dashboard']['actions']` 移除 `'detail' => '詳細頁'`：
    ```php
    'Dashboard' => [
        'label' => '儀表板',
        'actions' => [
            'index' => '首頁',
        ],
    ],
    ```
- [x] 5.2 確認 `syncPermissions` 既有 detach + delete 邏輯（[PermissionSeeder.php:106-110](database/seeders/PermissionSeeder.php#L106-L110)）會自動處理移除。**無需**寫 migration。

## 6. 測試

建立 `tests/Feature/DashboardTest.php`，使用 `RefreshDatabase` + `PermissionSeeder`，沿用 [ConferenceCrudTest](tests/Feature/Conference/ConferenceCrudTest.php) 的 helper（`createUserWithRole`、`loadPermissionsToSession`）。**所有測試的 setUp** SHALL 呼叫 `Carbon::setTestNow('2026-05-09 10:00:00', 'Asia/Taipei')`，並在 `tearDown` 呼叫 `Carbon::setTestNow()` 清除。

- [x] 6.1 存取權限
    - [x] 6.1.1 未登入 GET `/` → `assertRedirect('/login')`
    - [x] 6.1.2 已登入 Admin GET `/` → 200，view name 為 `admin.dashboard`
    - [x] 6.1.3 已登入但無 `Dashboard.index` 權限 → `assertRedirect` 至其 `default_route`（建立一個沒有 Dashboard 權限的角色）
- [x] 6.2 今日新增商店
    - [x] 6.2.1 只看到自己 `sales_id`：建立 A、B 兩個 user，各自 1 筆商店；A GET `/` → 只看到 A 的
    - [x] 6.2.2 不含昨日：建立 1 筆 `created_at = 2026-05-09 09:00 +08:00` 與 1 筆 `created_at = 2026-05-08 23:00 +08:00`，斷言只回 today 那筆
    - [x] 6.2.3 「今日」邊界以 Asia/Taipei 計算：`setTestNow('2026-05-09 02:00:00', 'Asia/Taipei')`，建立 1 筆 `created_at = 2026-05-09 01:00 +08:00`，應出現
    - [x] 6.2.4 admin 為 null 時 contact 顯示 `—`：建立 1 筆 shop（不建 ShopAdmin），view `assertSee('—')`
- [x] 6.3 全公司今日說明會
    - [x] 6.3.1 不依 user 過濾：建立 2 場今日 conferences，A、B 兩個 user 各 GET `/` 都看到 2 筆
    - [x] 6.3.2 不含明日：建立 1 場 today、1 場 tomorrow，只回 today
    - [x] 6.3.3 標題字面：view `assertSee('全公司今日說明會')`，**不**`assertSee('我的')`
- [x] 6.4 即將到期商店
    - [x] 6.4.1 只看到自己：建立 A、B 各 1 筆 `expired_at = 2026-06-08 12:00 +08:00`；A 只看到自己的
    - [x] 6.4.2 不含已過期：建立 1 筆 `expired_at = 2026-05-08 12:00 +08:00`，不應出現
    - [x] 6.4.3 不含半年外：建立 1 筆 `expired_at = 2026-12-31 12:00 +08:00`，不應出現
    - [x] 6.4.4 半年端點包含：建立 1 筆 `expired_at = 2026-11-09 23:59:59 +08:00`，應出現
- [x] 6.5 剩餘天數計算 + 色階（單元測試 DashboardService 直接斷言）
    - [x] 6.5.1 30 天 → days=30, color=`#ef4444`
    - [x] 6.5.2 60 天 → days=60, color=`#ef4444`
    - [x] 6.5.3 75 天 → days=75, color=`#f97316`
    - [x] 6.5.4 90 天 → days=90, color=`#f97316`
    - [x] 6.5.5 150 天 → days=150, color=`#ca8a04`
- [x] 6.6 Row 跳轉與權限 gate
    - [x] 6.6.1 有 `Shop.update` 權限：商店 row 為 `<a href="...shops.edit...">` link
    - [x] 6.6.2 無 `Shop.update` 權限：商店 row 不含該 link（Viewer 角色）
    - [x] 6.6.3 有 `Conference.update` 權限：conference row 為 `<a href="...conferences.edit...">`
    - [x] 6.6.4 無 `Conference.update` 權限：conference row 不含該 link
- [x] 6.7 預設展開狀態
    - [x] 6.7.1 `<details open>` 出現 2 次（今日新增商店與全公司今日說明會）
    - [x] 6.7.2 `<details class=`（無 open）出現 1 次（即將到期商店）
- [x] 6.8 Empty state：A 無任何資料時，view `assertSee` 三條文案
- [x] 6.9 PermissionSeeder 同步移除 Dashboard.detail
    - [x] 6.9.1 預先插入 `Permission(name='Dashboard.detail')`；跑 seeder；斷言不存在
    - [x] 6.9.2 同一測試斷言 `Dashboard.index` 仍存在

- [x] 6.10 Docker 內執行 `docker compose exec backend-api php artisan test --compact --filter=DashboardTest` 全綠（29 tests, 45 assertions）

## 7. 收尾

- [x] 7.1 Docker 內執行 `docker compose exec backend-api vendor/bin/pint --dirty --format agent` 通過
- [x] 7.2 執行 `docker compose exec backend-api openspec validate add-dashboard --strict` 通過
- [ ] 7.3 手動驗證 `/`：以擁有 / 不擁有 `Shop.update` 兩個角色登入，確認 row 為 `<a>` / `<span>`；點開展開／收起；切到其他頁面正常
- [ ] 7.4 OpenSpec archive：`openspec archive add-dashboard`（待 user 確認後）
