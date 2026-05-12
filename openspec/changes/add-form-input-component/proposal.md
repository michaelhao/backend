## Why

後台表單在 focus 狀態下，輸入文字幾乎貼住邊框，視覺壓迫、輸入不舒適。根因不是 focus 樣式本身，而是各 Blade 表單的原生 `<input>` / `<select>` 從未設定水平/垂直 padding（專案未安裝 `@tailwindcss/forms`），瀏覽器原生 padding 僅 1~2px，focus 時邊框轉藍 + ring 出現才把「字貼邊」放大為視覺問題。現有 `<x-password-input>` 元件已有 `px-3 py-2`、視覺正常，可作為對照基準。

本次以「抽 shared CSS class + 元件化封裝」一次解決 `<input>` 與 `<select>` 同源視覺問題，並讓既有 `<x-password-input>` / `<x-searchable-select>` 也吃同一個 base，達到家族一致。

## What Changes

- 新增 `.form-control` 全域 CSS named class，集中定義文字類 input / select / password / searchable-select 共用的基底樣式（`px-3 py-2`、border、focus ring 等）
- 新增 `<x-form-input>` Blade 元件
- 新增 `<x-form-select>` Blade 元件（純 slot wrapper）
- `<x-password-input>` 內部改用 `.form-control`
- `<x-searchable-select>` 內部 search input 改用 `.form-control`
- 將 14 個 Blade 檔的文字類 `<input>`（type 為 text / number / email / url / tel / date / search）與 `<select>` 一次遷移為 `<x-form-input>` / `<x-form-select>`
- 不變更 focus ring / border 顏色設定
- 不影響 `<textarea>`、`<input type="file" / checkbox / radio>`
- 不引入 `@tailwindcss/forms` plugin

## Capabilities

### New Capabilities
- `form-components`: 後台與 auth 頁面共用的表單欄位 Blade 元件集合 + shared `.form-control` CSS base（本次收斂 `<x-form-input>`、`<x-form-select>`；未來可擴 `<x-form-textarea>` 等）

### Modified Capabilities
（無——本次不修改既有 capability 的需求）

## Impact

- 新增檔案：
  - `resources/css/components/form.css`
  - `resources/views/components/form-input.blade.php`
  - `resources/views/components/form-select.blade.php`
- 修改 CSS：
  - `resources/css/app.css`（新增 `@import './components/form.css';`）
- 修改既有元件：
  - `resources/views/components/password-input.blade.php`（預設 class 改吃 `.form-control`）
  - `resources/views/components/searchable-select.blade.php`（內部 search input 改吃 `.form-control`）
- 修改檔案（14 個 Blade）：
  - `resources/views/admin/addons/_form.blade.php`
  - `resources/views/admin/conferences/_form.blade.php`
  - `resources/views/admin/users/_form.blade.php`
  - `resources/views/admin/grades/_form.blade.php`
  - `resources/views/admin/roles/_form.blade.php`
  - `resources/views/admin/bills/create.blade.php`
  - `resources/views/admin/shops/edit.blade.php`
  - `resources/views/admin/addons/index.blade.php`（filter 列）
  - `resources/views/admin/conferences/index.blade.php`（filter 列）
  - `resources/views/admin/bills/index.blade.php`（filter 列）
  - `resources/views/admin/shops/index.blade.php`（filter 列）
  - `resources/views/auth/login.blade.php`
  - `resources/views/auth/forgot-password.blade.php`
  - `resources/views/auth/reset-password.blade.php`
- 無資料庫遷移、無路由變更、無新 npm 依賴、無新 composer 依賴
- 前端需重新 build（`npm run build` 或 `composer run dev`）