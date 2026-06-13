# design — retrofit-admin-layout-spec

本 change 為既有功能的 retroactive spec 回填，記錄關鍵設計決策的判定理由。

## 威脅模型脈絡

內部後台、非公網暴露 → brute-force 類風險不在 scope；正式環境無對外網際網路連線 → 版型零外部資源依賴。本功能不發任何外部呼叫。

## 設計決策

### D1. session 計時器為純前端倒數，刻意不做 activity-based reset
`admin.js` 從 `session-lifetime` meta 起倒數，不在使用者操作時重置，歸零導向 `/login`。判定為**刻意設計**而非缺陷：伺服器端 session 失效仍由 Laravel session middleware 權威處理，前端計時器僅為「剩餘時間視覺提示 + 到期自動跳轉」的便利性。寫成 MUST NOT requirement 而非當 bug 修。

### D2. sidebar 固定 w-64、無 RWD
桌面內部後台使用情境，刻意不實作行動裝置 sidebar 收合 / 漢堡選單。寫成 MUST NOT requirement。

### D3. 「文章管理」佔位連結移除（行為修正）
舊 spec 2 的「Posts 預留」項目以 `<x-permission name="Post.index">` 包裹並連到 `href="#"`。`PermissionSeeder` 無 `Post.index` 權限 → 對所有角色永不渲染，屬死碼。Phase 2 移除（commit `f0d8bdc`），spec 既有「視覺主題」requirement 的選單數由 9 → 8。

### D4. 計時器色彩 token 不一致修正（bug fix）
markup 計時 span 用 `text-slate-400`，但 `admin.js` 在 ≤300 秒時 `remove('text-gray-400')`（Tailwind v3 殘留命名）為 no-op 死碼。Phase 2 統一為 `text-slate-400`（commit `5ea8aa7`），並將既有「灰階 token 統一 slate-*」約束擴及 JS 計時器。

### D5. 無角色頁的 middleware 邊界
`GET /no-role` 受 `auth` 保護但**不**進 `permission` middleware 群組——若進群組，無角色者會在 redirect 目標上再次被導向而成迴圈。導向觸發邏輯屬 permission middleware（已於 PermissionTest 覆蓋），本 spec 僅規範頁面渲染與路由邊界。

## 不在本範疇

- 權限判定 / 載入 session（`auth.permissions`）的完整邏輯屬 auth / 權限 capability，本 spec 僅以 `<x-permission>` 消費端 cross-reference。
- Dashboard 內容（統計卡片、到期色階）屬 `dashboard` capability，本 spec 僅規範其承載外殼。
