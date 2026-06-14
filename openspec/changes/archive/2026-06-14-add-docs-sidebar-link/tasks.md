## 1. 側欄連結

- [x] 1.1 在 `resources/views/layouts/admin.blade.php` 的 `<nav>` 開標籤之後、`<x-permission name="Dashboard.index">` 之前,新增一段 `<a href="{{ route('docs.index') }}">` 連結,文字「系統文件」,**不包** `<x-permission>`
- [x] 1.2 以 Blade `@env('local') ... @endenv` 包住該連結,使其僅在 `local` 環境渲染
- [x] 1.3 active 樣式以 `request()->routeIs('docs.*')` 判斷,套用與既有導覽連結一致的 class(active: `bg-blue-50 text-blue-600`;非 active: `text-slate-500 hover:bg-slate-100 hover:text-slate-700`)

## 2. 測試

- [x] 2.1 在 `tests/Feature/DocsTest.php` 新增 `test_docs_sidebar_link_hidden_outside_local`:plain `User::factory()` GET `/docs`(預設 `testing` 環境),`assertDontSee('href="'.route('docs.index').'"', false)`
- [x] 2.2 在 `tests/Feature/DocsTest.php` 新增 `test_docs_sidebar_link_visible_in_local`:先 `$this->app['env'] = 'local';`,plain `User::factory()` GET `/docs`,`assertSee('href="'.route('docs.index').'"', false)`
- [x] 2.3 執行 `docker compose exec backend-api php artisan test --compact tests/Feature/DocsTest.php`,確認全數通過(含既有 5 個)

## 3. 收尾

- [x] 3.1 目視確認(`local`):登入後 sidebar 最上方出現「系統文件」,點擊導向 `/docs`,於 `/docs` 呈現 active 高亮(連結渲染/隱藏已由 `test_docs_sidebar_link_visible_in_local` / `_hidden_outside_local` 自動化覆蓋;active class 由 `routeIs('docs.*')` 決定,留人工瀏覽器最終確認)
- [x] 3.2 `openspec validate add-docs-sidebar-link --strict` 通過
