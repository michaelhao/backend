## 1. 修改 layout

- [ ] 1.1 修改 [resources/views/layouts/admin.blade.php](resources/views/layouts/admin.blade.php)：
    - `<aside>` class：`bg-gray-900 text-gray-100` → `bg-white text-slate-700 border-r border-slate-200`
    - logo block：移除 `border-b border-gray-800`（**不加** ADMIN PANEL 副標）
    - Logo / 站名顏色：`text-violet-600`
    - 9 個 `<a>` 連結：
        - active 樣式：`bg-gray-800 text-white` → `bg-violet-50 text-violet-600`（保留 `font-medium`，不改字重）
        - 非 active 樣式：`text-gray-400 hover:bg-gray-800 hover:text-white` → `text-slate-500 hover:bg-slate-100 hover:text-slate-700`
    - top bar `<header>`：`border-gray-200` → `border-slate-200`；`text-gray-800` → `text-slate-800`；`text-gray-400` → `text-slate-400`；`text-gray-600` → `text-slate-600`；logout 按鈕 `text-gray-500 hover:text-gray-700` → `text-slate-500 hover:text-slate-700`
- [ ] 1.2 9 個選單項目**全保留**，不採用設計稿 mock 的 4 項
- [ ] 1.3 active 判定邏輯（既有 `request()->routeIs(...)` 或 `Request::path()` 等）**不**動

## 2. 視覺回歸（9 頁）

逐頁登入檢視 active / hover 樣式，不應殘留任何深色：

- [ ] 2.1 `/`（儀表板）
- [ ] 2.2 `/users`（使用者管理）
- [ ] 2.3 `/roles`（角色管理）
- [ ] 2.4 `/grades`（版本管理）
- [ ] 2.5 `/shops`（商店管理）
- [ ] 2.6 `/bills`（帳務管理）
- [ ] 2.7 `/addons`（加購功能管理）
- [ ] 2.8 `/conferences`（說明會管理）
- [ ] 2.9 文章管理（路徑待確認，placeholder）

每頁需確認：
- 該頁對應選單項為 active 紫色樣式
- 其他選單項為灰色 + 紫底 hover
- 內容區與 sidebar 對比足夠（白底 sidebar + 灰底 content `bg-gray-100` 已有對比）

## 3. 收尾

- [ ] 3.1 docker 內跑 `docker compose exec backend-api php artisan test --compact`（純視覺改動但跑一次保險）
- [ ] 3.2 docker 內跑 `npm run build` 確認 Tailwind 產出新 class
- [ ] 3.3 執行 `openspec validate refresh-admin-sidebar --strict` 通過
- [ ] 3.4 OpenSpec archive：`openspec archive refresh-admin-sidebar`
- [ ] 3.5 確認沒殘留深色 token：`docker compose exec backend-api grep -nE "bg-gray-|text-gray-|border-gray-|hover:bg-gray-|hover:text-gray-" resources/views/layouts/admin.blade.php` 應為 0 結果
