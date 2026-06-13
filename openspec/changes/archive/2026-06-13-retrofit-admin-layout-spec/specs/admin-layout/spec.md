# admin-layout Specification (delta)

## MODIFIED Requirements

### Requirement: Admin Sidebar 視覺主題

[layouts/admin.blade.php](resources/views/layouts/admin.blade.php) 的 sidebar SHALL 採用淺色 + 藍品牌主題：

- 容器：白底 + 右側細邊框（`bg-white border-r border-slate-200`）
- Logo block：藍色品牌色站名（`text-blue-600`）；不留 `border-b`
- 導覽連結 active 樣式：藍色淺底（`bg-blue-50`）+ 藍色文字（`text-blue-600`），字重維持 `font-medium`
- 導覽連結非 active：`text-slate-500 hover:bg-slate-100 hover:text-slate-700`
- top bar `<header>` 與 session 計時器（`resources/js/layouts/admin.js`）的灰階 token SHALL 統一使用 `slate-*`（不使用 `gray-*`），藍色不下放
- 8 個既有選單（儀表板 / 使用者管理 / 角色管理 / 版本管理 / 商店管理 / 加購功能管理 / 帳務管理 / 說明會管理）SHALL 全部保留；早期預留的「文章管理」（`Post.index`）佔位連結已移除（該權限不存在於 `PermissionSeeder`，對所有角色永不渲染）

#### Scenario: 進入儀表板時 active 樣式
- **WHEN** 使用者 GET `/`
- **THEN** sidebar 中「儀表板」連結含 `text-blue-600` 樣式

#### Scenario: 切到其他頁面時 sidebar 樣式仍為淺色
- **WHEN** 使用者 GET `/users`、`/shops` 等任一頁面
- **THEN** sidebar 維持白底淺色藍品牌
- **AND** 該頁面對應選單為 active 藍色樣式

#### Scenario: 8 個選單全保留
- **WHEN** admin 角色使用者開啟 admin 頁面
- **THEN** sidebar 顯示全部 8 項既有選單（儀表板、使用者管理、角色管理、版本管理、商店管理、加購功能管理、帳務管理、說明會管理），順序與既有相同
- **AND** 不再出現「文章管理」佔位連結

## ADDED Requirements

### Requirement: 後台版型外殼

所有後台頁面 SHALL 繼承 [layouts/admin.blade.php](resources/views/layouts/admin.blade.php)，由其提供固定左側 sidebar（`w-64`）+ 頂部列 + 主內容區的三段式骨架。版型外殼 SHALL 提供：

- `@yield('content')` 主內容插槽
- `@yield('page-title', 'Dashboard')` 同時用於 `<title>` 與頂部列標題
- `@stack('scripts')` 供子頁面注入頁面層級 JS
- `<head>` 內 `csrf-token` 與 `session-lifetime`（值 = `config('session.lifetime') * 60` 秒）兩個 meta
- 透過 `@vite` 載入 `resources/css/app.css`、`resources/js/app.js`、`resources/js/layouts/admin.js`

#### Scenario: 後台頁面套用版型外殼
- **GIVEN** 已登入且具 `Dashboard.index` 權限的使用者
- **WHEN** GET `/`
- **THEN** 系統回應 200 並渲染 `admin.dashboard` 視圖（繼承 `layouts.admin`）
- **AND** 回應含 sidebar、頂部列與主內容區

#### Scenario: session-lifetime meta 以秒注入
- **GIVEN** 已登入使用者
- **WHEN** 開啟任一後台頁面
- **THEN** `<head>` 含 `<meta name="session-lifetime">`，其值等於 `config('session.lifetime') * 60`

---

### Requirement: 權限導向的側邊欄導覽

sidebar 每個選單連結 SHALL 以 `<x-permission name="{Module}.index">` 元件包裹，僅在當前使用者持有該權限時渲染。`Permission` 元件的 `shouldRender()` SHALL 回傳 `Auth::check() && $user->hasPermissionTo($name)`，其中權限來源為登入時載入的 `session('auth.permissions')`。當前路由 active 標示 SHALL 以 `request()->routeIs('{module}.*')` 判斷。

#### Scenario: 有權限者可見對應選單
- **GIVEN** 持有 `Shop.index` 權限的使用者
- **WHEN** 開啟後台頁面
- **THEN** sidebar 顯示「商店管理」連結

#### Scenario: 無權限者不見對應選單
- **GIVEN** 未持有 `Shop.index` 權限的使用者
- **WHEN** 開啟後台頁面
- **THEN** sidebar 不渲染「商店管理」連結

#### Scenario: 未登入時元件不渲染
- **GIVEN** 未通過認證的請求情境
- **WHEN** `<x-permission>` 嘗試渲染
- **THEN** `shouldRender()` 因 `Auth::check()` 為 false 而回傳 false，不渲染 slot

---

### Requirement: 頂部列使用者識別與登出

頂部列 SHALL 顯示當前頁面標題、`Auth::user()->name`、session 倒數計時器，以及一個 POST 至 `route('logout')` 的登出表單（含 `@csrf`）。

#### Scenario: 顯示登入者姓名
- **GIVEN** 名為「王小明」的已登入使用者
- **WHEN** 開啟後台頁面
- **THEN** 頂部列顯示「王小明」

#### Scenario: 登出表單以 POST 提交
- **WHEN** 渲染頂部列
- **THEN** 含 `method="POST"` 且 `action` 指向 `route('logout')` 的表單與 CSRF token

---

### Requirement: Session 倒數計時器

[resources/js/layouts/admin.js](resources/js/layouts/admin.js) SHALL 讀取 `session-lifetime` meta 作為起始秒數，於頂部列以 `HH:MM:SS` 每秒遞減顯示。剩餘秒數 ≤ 300 時顯示文字 SHALL 由 `text-slate-400` 切換為 `text-red-500`；歸零時 SHALL 將瀏覽器導向 `/login`。計時器顏色 class SHALL 與 markup 一致採用 `slate-*`（修正先前 `text-gray-400` 的 no-op remove 死碼）。

#### Scenario: 倒數歸零導向登入頁
- **GIVEN** 後台頁面載入且計時器啟動
- **WHEN** 剩餘秒數遞減至 0
- **THEN** 瀏覽器導向 `/login`

#### Scenario: 進入警示門檻變紅
- **GIVEN** 計時器運作中
- **WHEN** 剩餘秒數 ≤ 300
- **THEN** 計時文字移除 `text-slate-400` 並加上 `text-red-500`

> 註：此為純前端視覺提示，伺服器端 session 失效仍由 Laravel session middleware 處理。

---

### Requirement: 無角色 fallback 頁

`GET /no-role`（route name `no-role`）SHALL 渲染無角色提示頁，繼承 `layouts.admin`，並提供 POST 登出表單。此路由 SHALL 受 `auth` 保護但 **不** 進入 `permission` middleware 群組（避免無角色者被反覆導向）。當已登入使用者 `role_id` 為 null 時，`permission` middleware SHALL 將其導向此頁。

#### Scenario: 無角色使用者被導向 no-role 頁
- **GIVEN** 已登入但 `role_id` 為 null 的使用者
- **WHEN** GET `/`
- **THEN** 系統 302 導向 `route('no-role')`

#### Scenario: no-role 頁提供登出
- **WHEN** 渲染 `/no-role`
- **THEN** 頁面含 POST 至 `route('logout')` 的登出表單

---

### Requirement: 訪客版型

登入、忘記密碼、重設密碼等 guest 頁面 SHALL 繼承 [layouts/app.blade.php](resources/views/layouts/app.blade.php)——置中、無 sidebar、僅載入 `app.css` 與 `app.js` 的精簡骨架，與後台 `layouts.admin` 分離。

#### Scenario: 登入頁使用訪客版型
- **WHEN** 訪客 GET `/login`
- **THEN** 頁面以 `layouts.app` 渲染（置中、無 sidebar）

---

### Requirement: 刻意不做的範圍界線

基於內部後台、非公網暴露的威脅模型與簡化考量，本版型 SHALL 明確界定下列 **刻意不實作** 的範圍：

- 版型 MUST NOT 提供 session 活動式重置 / heartbeat：計時器為單純倒數，不因使用者操作而重置（伺服器端到期仍由 Laravel session 處理）
- 版型 MUST NOT 提供 RWD 行動裝置 sidebar 收合 / 漢堡選單：sidebar 固定 `w-64`，僅針對桌面內部後台

#### Scenario: 計時器不因操作重置
- **GIVEN** 後台頁面開啟且計時器運作中
- **WHEN** 使用者在頁面內持續操作（未換頁）
- **THEN** 計時器持續倒數，不重置回起始值

#### Scenario: 無行動裝置 sidebar 切換
- **WHEN** 檢視版型 markup
- **THEN** 不存在 sidebar 收合 / 漢堡選單的切換控制
