## Why

`/docs` 系統文件的路由與頁面早已存在（`routes/web.php` 的 `docs.index`、`resources/views/docs/index.blade.php`），但 admin sidebar 沒有任何指向它的入口，使用者只能手動輸入網址才到得了。需補上側欄入口,讓所有登入者都能直接點進系統文件。

## What Changes

- 在 admin sidebar 導覽列（`<nav>`）**最上方**（「儀表板」之前）新增一個「系統文件」連結，指向 `route('docs.index')`。
- 此連結 **僅在 `local` 環境顯示**（系統文件為開發人員導向），以 Blade `@env('local')` 包裹;正式環境不渲染。
- 此連結 **不** 以 `<x-permission>` 包裹：因 `/docs` 刻意置於 `permission` middleware 之外（登入即可瀏覽、不進權限系統），在 `local` 環境對 **所有登入者** 皆顯示，與其餘權限導向的管理選單不同。
- active 樣式沿用既有導覽連結慣例，以 `request()->routeIs('docs.*')` 判斷，套用 `bg-blue-50 text-blue-600`。
- **僅藏連結,不動路由**:`/docs` 路由維持各環境皆可(內部後台威脅模型下風險可接受),既有 `DocsTest` 不受影響。

## Capabilities

### New Capabilities
<!-- 無新 capability -->

### Modified Capabilities
- `admin-layout`: 「權限導向的側邊欄導覽」需求新增例外——側欄除權限控管選單外，SHALL 在 `local` 環境包含一個對所有登入者可見、不經權限系統的「系統文件」連結置於導覽最上方;非 `local` 環境不渲染。

## Impact

- `resources/views/layouts/admin.blade.php`（唯一程式碼變更：以 `@env('local')` 包裹一段 `<a>` 連結）。
- 測試：`tests/Feature/DocsTest.php`（新增兩個 feature test,分別斷言 testing 環境不出現、強制 `local` 環境出現此入口）。
- 不動路由、controller、CSS token；不引入新 utility class。
