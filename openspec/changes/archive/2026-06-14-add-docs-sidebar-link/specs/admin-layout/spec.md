## MODIFIED Requirements

### Requirement: 權限導向的側邊欄導覽

sidebar 每個 **權限控管** 選單連結 SHALL 以 `<x-permission name="{Module}.index">` 元件包裹，僅在當前使用者持有該權限時渲染。`Permission` 元件的 `shouldRender()` SHALL 回傳 `Auth::check() && $user->hasPermissionTo($name)`，其中權限來源為登入時載入的 `session('auth.permissions')`。當前路由 active 標示 SHALL 以 `request()->routeIs('{module}.*')` 判斷。不進權限系統的連結（例如「系統文件」入口，見對應需求）SHALL NOT 以 `<x-permission>` 包裹。

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

## ADDED Requirements

### Requirement: 系統文件側邊欄入口

在 `local` 環境,sidebar 導覽列（`<nav>`）最上方（位於「儀表板」之前）SHALL 包含一個指向 `route('docs.index')` 的「系統文件」連結;此連結為開發人員導向,SHALL 以 Blade `@env('local')` 包裹,於 **非 `local` 環境 SHALL NOT 渲染**。因 `/docs` 路由置於 `permission` middleware 之外（登入即可瀏覽、不進權限系統），此連結 SHALL NOT 以 `<x-permission>` 包裹，且在 `local` 環境 SHALL 對所有已登入使用者顯示（含無任何管理權限者）。其 active 標示 SHALL 以 `request()->routeIs('docs.*')` 判斷，套用與其他導覽連結一致的 `bg-blue-50 text-blue-600` 樣式。

#### Scenario: local 環境一般登入者可見系統文件入口
- **GIVEN** `local` 環境下已登入但未持有任何管理權限的使用者
- **WHEN** GET `/docs`
- **THEN** sidebar 顯示指向 `route('docs.index')` 的「系統文件」連結

#### Scenario: 非 local 環境不顯示系統文件入口
- **GIVEN** 非 `local` 環境（如 `production`、`testing`）的已登入使用者
- **WHEN** GET `/docs`
- **THEN** sidebar 不渲染「系統文件」連結

#### Scenario: 系統文件入口不受權限控管
- **WHEN** 於 `local` 環境渲染 sidebar
- **THEN** 「系統文件」連結未被 `<x-permission>` 包裹，對所有登入者一致顯示

#### Scenario: 進入文件頁時 active 樣式
- **WHEN** 使用者於 `local` 環境 GET `/docs`
- **THEN** sidebar 中「系統文件」連結含 `bg-blue-50 text-blue-600` 樣式
