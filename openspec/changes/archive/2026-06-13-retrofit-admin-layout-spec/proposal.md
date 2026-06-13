# retrofit-admin-layout-spec

## Why

後台共用版型（admin-layout：sidebar 外殼 / 權限導向導覽 / 頂部列登出 / session 倒數計時器 / 無角色 fallback 頁 / 訪客版型）的文件仍停留在原始實作計畫 [spec/2-backendLayout.md](../../../spec/2-backendLayout.md)，且 `openspec/specs/admin-layout/spec.md` 僅有早期 `refresh-admin-sidebar` 留下的「視覺主題」單一 Requirement（Purpose 仍為 TBD）。實際實作已大幅演進：sidebar 從舊 spec 的「Dashboard / Posts / Users 預留」擴充為 8 個權限 gating 的模組連結，並新增 session 倒數計時器與無角色頁。

2026-06-13 設計 review 後完成 2 項修正：1 個版型死碼移除（sidebar `Post.index`「文章管理」佔位連結，權限不存在故永不渲染）、1 個前端 bug 修正（session 計時器 `text-gray-400` no-op remove → 統一 `text-slate-400`），commit `f0d8bdc`、`5ea8aa7`。

本 change 為**文件回填**：將舊版 spec 與設計 review 修正後的實際行為合併為一份反映現狀的 `admin-layout` capability spec，並汰除被取代的舊 spec 檔。

## What Changes

- 更新 `admin-layout` capability spec，涵蓋現狀行為：
    - **(MODIFIED)** Admin Sidebar 視覺主題：選單由 9 → **8 項**（移除「文章管理」佔位）；灰階 token 統一 `slate-*` 的約束擴及 `admin.js` 計時器
    - **(ADDED)** 後台版型外殼：`@yield('content')`/`@yield('page-title')`/`@stack('scripts')`、`csrf-token` 與 `session-lifetime`（`lifetime*60` 秒）meta、`@vite` 載入
    - **(ADDED)** 權限導向側邊欄導覽：每個連結以 `<x-permission name="{Module}.index">` 包裹，`shouldRender()` = `Auth::check() && hasPermissionTo()`；active 以 `routeIs()` 判斷
    - **(ADDED)** 頂部列使用者識別與登出（POST `route('logout')` + CSRF）
    - **(ADDED)** session 倒數計時器：讀 `session-lifetime` meta 倒數、≤300 秒變紅、歸零導向 `/login`
    - **(ADDED)** 無角色 fallback 頁：`GET /no-role` 受 `auth` 保護但不進 `permission` 群組
    - **(ADDED)** 訪客版型 `layouts/app.blade.php`（置中、無 sidebar）
    - **(ADDED)** 刻意不做（MUST NOT）：無 session 活動式重置 / heartbeat、無 RWD 行動 sidebar 收合
- 刪除 `spec/2-backendLayout.md`（由本 spec 取代）
- **文件階段無新增程式變更**：對應實作已在 commit `f0d8bdc`、`5ea8aa7` 完成並通過 DashboardTest

## Capabilities

### New Capabilities
<!-- 無全新 capability；admin-layout 已存在，本 change 為回填補全。 -->

### Modified Capabilities
- `admin-layout`: 後台共用版型外殼、權限導向導覽、頂部列登出、session 倒數計時器、無角色 fallback 頁與訪客版型的完整行為規格（內部後台、非公網暴露的威脅模型；正式環境無對外連線，本功能無任何外部呼叫）

## Impact

- **程式碼**：spec 描述的現狀實作位於 `resources/views/layouts/admin.blade.php`、`resources/views/layouts/app.blade.php`、`resources/views/no-role.blade.php`、`resources/views/components/permission.blade.php`、`app/View/Components/Permission.php`、`resources/js/layouts/admin.js`
- **文件**：更新 `openspec/specs/admin-layout/spec.md`（archive 後）；刪除 `spec/2-backendLayout.md`
- **測試**：版型外殼渲染由 `tests/Feature/DashboardTest.php`（`/` 套用 `layouts.admin`）、無角色導向由 `tests/Feature/PermissionTest.php::test_user_without_role_is_redirected_to_no_role_page` 間接覆蓋；session 計時器為純前端行為，無 PHP 測試
