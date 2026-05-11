## Why

Claude Design 出的「店務管理儀表板」設計稿（方案 B 淺色版）確立淺色藍為品牌方向，但既有 [layouts/admin.blade.php](resources/views/layouts/admin.blade.php) sidebar 仍使用深色 `bg-gray-900 text-gray-100`，與設計稿配色斷裂。

## What Changes

- 修改 [resources/views/layouts/admin.blade.php](resources/views/layouts/admin.blade.php)：
    - sidebar `<aside>`：`bg-gray-900 text-gray-100` → `bg-white text-slate-700 border-r border-slate-200`
    - logo block：移除 `border-b border-gray-800`（不加副標）
    - Logo / 站名：藍色品牌色 `text-blue-600`
    - 導覽連結 active：`bg-gray-800 text-white` → `bg-blue-50 text-blue-600`（**不**改字重，維持 `font-medium`）
    - 導覽連結非 active：`text-gray-400 hover:bg-gray-800 hover:text-white` → `text-slate-500 hover:bg-slate-100 hover:text-slate-700`
    - top bar `<header>` 灰系統一 `gray-*` → `slate-*`（`border-gray-200` → `border-slate-200`；`text-gray-800/600/500/400` → 對應 `slate-*`；`hover:text-gray-700` → `hover:text-slate-700`）
- 9 個既有選單項目**全部保留**（儀表板 / 文章管理 / 使用者管理 / 角色管理 / 版本管理 / 商店管理 / 加購功能管理 / 帳務管理 / 說明會管理）。
- 視覺回歸：以 Admin 與 Viewer 兩個 role 各登入一次，逐一檢視 9 個既有 admin 頁面，確認 active/hover 樣式正常、沒有殘留深色。

## Capabilities

### New Capabilities
<!-- 無 -->

### Modified Capabilities
- `admin-layout`: admin 共用版型的 sidebar 配色

## Impact

- **資料庫 / 路由 / 權限**：無變動。
- **程式碼**：僅修改 `resources/views/layouts/admin.blade.php` 一檔（純 utility class 變動）。
- **視覺回歸範圍**：9 個既有 admin 頁面（`/`、`/users`、`/roles`、`/grades`、`/shops`、`/bills`、`/addons`、`/conferences`、文章管理 placeholder）。
- **Rollback**：revert 一個檔案。

## 相依與相關 change

- 與 `add-dashboard` 完全獨立，可並行。
- 與 `change-app-timezone-to-taipei` 完全獨立。
