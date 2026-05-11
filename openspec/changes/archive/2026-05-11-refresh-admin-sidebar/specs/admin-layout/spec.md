## ADDED Requirements

### Requirement: Admin Sidebar 視覺主題

[layouts/admin.blade.php](resources/views/layouts/admin.blade.php) 的 sidebar SHALL 採用淺色 + 藍品牌主題：

- 容器：白底 + 右側細邊框（`bg-white border-r border-slate-200`）
- Logo block：藍色品牌色站名（`text-blue-600`）；不留 `border-b`
- 導覽連結 active 樣式：藍色淺底（`bg-blue-50`）+ 藍色文字（`text-blue-600`），字重維持 `font-medium`
- 導覽連結非 active：`text-slate-500 hover:bg-slate-100 hover:text-slate-700`
- top bar `<header>` 灰階 token SHALL 統一使用 `slate-*`（不使用 `gray-*`），藍色不下放
- 9 個既有選單（儀表板 / 文章管理 / 使用者管理 / 角色管理 / 版本管理 / 商店管理 / 加購功能管理 / 帳務管理 / 說明會管理）SHALL 全部保留

#### Scenario: 進入儀表板時 active 樣式
- **WHEN** 使用者 GET `/`
- **THEN** sidebar 中「儀表板」連結含 `text-blue-600` 樣式

#### Scenario: 切到其他頁面時 sidebar 樣式仍為淺色
- **WHEN** 使用者 GET `/users`、`/shops` 等任一頁面
- **THEN** sidebar 維持白底淺色藍品牌
- **AND** 該頁面對應選單為 active 藍色樣式

#### Scenario: 9 個選單全保留
- **WHEN** 任一授權使用者開啟 admin 頁面
- **THEN** sidebar 顯示**有權限的**既有選單項目（admin role 應顯示全部 9 項：儀表板、文章管理、使用者管理、角色管理、版本管理、商店管理、加購功能管理、帳務管理、說明會管理），順序與既有相同
