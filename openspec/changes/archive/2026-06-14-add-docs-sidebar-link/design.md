## Context

`admin-layout` 既有規格規定 sidebar「每個選單連結 SHALL 以 `<x-permission name="{Module}.index">` 包裹」,僅在使用者持有對應權限時渲染。但 `/docs` 路由刻意置於 `permission` middleware 群組 **之外**（`routes/web.php`:「登入即可瀏覽、不進權限系統」),因此沒有對應的權限可供包裹。本變更需在不破壞既有權限導向慣例的前提下,加入這個跨權限的文件入口。

## Goals / Non-Goals

**Goals:**
- 在 `local` 開發環境,所有登入者(含無任何管理權限者)都能從 sidebar 直接進入系統文件。
- 樣式與既有導覽連結一致(active / hover token 沿用)。

**Non-Goals:**
- 不在正式環境顯示此連結(系統文件為開發人員導向)。
- 不為 `/docs` 建立權限(維持其「不進權限系統」的既有設計)。
- 不限制 `/docs` **路由** 的環境(只藏連結,見決策 4)。
- 不調整其餘 8 個權限控管選單的順序或樣式。
- 不做 RWD / 收合等版型行為(沿用既有界線)。

## Decisions

**決策 1:文件連結不以 `<x-permission>` 包裹,直接輸出 `<a>`。**
- 理由:`/docs` 無對應權限名稱;若硬包 `<x-permission name="...">`,`shouldRender()` 會因 `hasPermissionTo()` 找不到權限而回傳 false,導致對 **所有人** 隱藏——與「所有登入者可見」目標完全相反。
- 替代方案(已否決):新增 `Docs.index` 權限並指派給所有角色 → 增加權限系統負擔、違反 `/docs` 既有的「不進權限系統」設計,過度工程。

**決策 2:置於導覽列最上方(「儀表板」之前)。**
- 理由:作為對所有人可見的固定入口,放最上方語意清楚且不影響既有權限選單的相對順序;經與需求方確認。

**決策 3:active 判斷用 `request()->routeIs('docs.*')`,但 active 樣式實際上只在 `/docs`(`docs.index`)成立。**
- `docs.show` 回 `BinaryFileResponse`(原始 HTML 檔),不經 `layouts.admin`,該頁無 sidebar;`docs/index` 的文件連結又是 `target="_blank"` 開新分頁,主視窗永遠停在 `docs.index`。
- 程式碼寫 `docs.*` 與既有選單慣例一致且無副作用(`docs.show` 永不渲染側欄);但 spec 的 active scenario 收斂成只涵蓋 `/docs`,避免規格描述一個不可能成立的情境。

**決策 4:僅以 `@env('local')` 包裹連結,**不**限制 `/docs` 路由的環境。**
- 理由:系統文件為開發人員導向,正式環境不需顯示入口。Blade `@env('local') ... @endenv` 為 Laravel 慣用指令,比 `@if(app()->environment('local'))` 簡潔;`.env`/`.env.example` 皆 `APP_ENV=local`,開發者本機正確顯示。
- 為何不連路由一起鎖 `local`:若路由限定 `local`,`testing` 環境(`APP_ENV=testing`)該路由即不存在,既有 5 個 `DocsTest`(GET `/docs`、`/docs/{name}`)會全數壞掉,blast radius 過大;且內部後台、非公網暴露的威脅模型下,正式環境開著的 `/docs` URL 風險可接受。嚴格 dev-only 留待獨立變更處理。

## Risks / Trade-offs

- [既有規格「每個選單連結都包 `<x-permission>`」與此連結矛盾] → 於 spec 以 MODIFIED 收斂該需求範圍至「權限控管選單」,並以 ADDED 明確定義文件入口為例外,避免規格自相矛盾。
- [`@env('local')` 閘門使 `testing` 環境看不到連結,測試易誤判] → 寫兩個 feature test 覆蓋閘門兩側:預設 `testing` 環境斷言 `assertDontSee` href;`$this->app['env'] = 'local'` 強制 local 後斷言 `assertSee` href。斷言以 `href="...docs.index..."` markup 為準(非中文字 — `/docs` 頁面本身的 `<h2>系統文件</h2>` 會讓中文字斷言假通過)。
- [只藏連結、路由仍開] → 正式環境知道網址者仍可存取 `/docs`(security by obscurity);內部威脅模型下接受,必要時另開變更鎖路由。
