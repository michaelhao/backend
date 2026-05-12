## 1. CSS shared base

- [ ] 1.1 新增 `resources/css/components/form.css`，內含：
  ```css
  @layer components {
      .form-control {
          @apply rounded-lg border-gray-300 shadow-sm px-3 py-2
                 focus:border-blue-500 focus:ring-blue-500 text-sm;
      }
  }
  ```
- [ ] 1.2 修改 `resources/css/app.css`，在 `@import 'tailwindcss';` 後加入 `@import './components/form.css';`

## 2. 建立新元件

- [ ] 2.1 建立 `resources/views/components/form-input.blade.php`
  - Props: `name`（必填）、`id`（預設 `name`）、`type`（預設 `text`）、`value`（預設 `null`）
  - 使用 `$attributes->merge(['class' => 'form-control'])`
  - 永遠輸出 `value="{{ $value }}"`
- [ ] 2.2 建立 `resources/views/components/form-select.blade.php`
  - Props: `name`（必填）、`id`（預設 `name`）
  - 使用 `$attributes->merge(['class' => 'form-control'])`
  - 純 slot wrapper：`<select ...>{{ $slot }}</select>`
- [ ] 2.3 用 `php artisan view:clear` 後快速驗證元件渲染（class、id、type、value 預期值）

## 3. Refactor 既有元件

- [ ] 3.1 修改 `resources/views/components/password-input.blade.php`：預設 class 改為 `form-control w-full pr-10`，移除其他重複字串
- [ ] 3.2 修改 `resources/views/components/searchable-select.blade.php`：搜尋 `<input>` class 改為 `form-control w-full ss-input`（保留 `.ss-input` 給 JS 綁定）
- [ ] 3.3 grep 驗證 `resources/views/components/` 內已無 `border-gray-300` 殘留

## 4. 遷移 admin 表單（_form / create / edit）

- [ ] 4.1 遷移 `resources/views/admin/addons/_form.blade.php` 的文字 input 與 select
- [ ] 4.2 遷移 `resources/views/admin/conferences/_form.blade.php`
- [ ] 4.3 遷移 `resources/views/admin/users/_form.blade.php`
- [ ] 4.4 遷移 `resources/views/admin/grades/_form.blade.php`
- [ ] 4.5 遷移 `resources/views/admin/roles/_form.blade.php`
- [ ] 4.6 遷移 `resources/views/admin/bills/create.blade.php`
- [ ] 4.7 遷移 `resources/views/admin/shops/edit.blade.php`

## 5. 遷移 admin index 頁 filter 列

- [ ] 5.1 遷移 `resources/views/admin/addons/index.blade.php` 的 filter input / select（注意保留原寬度 class）
- [ ] 5.2 遷移 `resources/views/admin/conferences/index.blade.php`
- [ ] 5.3 遷移 `resources/views/admin/bills/index.blade.php`
- [ ] 5.4 遷移 `resources/views/admin/shops/index.blade.php`

## 6. 遷移 auth 頁面

- [ ] 6.1 遷移 `resources/views/auth/login.blade.php`（不含 password — 維持 `<x-password-input>`）
- [ ] 6.2 遷移 `resources/views/auth/forgot-password.blade.php`
- [ ] 6.3 遷移 `resources/views/auth/reset-password.blade.php`（不含 password — 維持 `<x-password-input>`）

## 7. 範圍驗收

- [ ] 7.1 `grep -rln 'border-gray-300' resources/views --exclude-dir=components` 命中 0 或僅剩 file/checkbox/radio
- [ ] 7.2 `grep -rln 'border-gray-300' resources/views/components` 命中 0（password-input / searchable-select 已改吃 `.form-control`）
- [ ] 7.3 確認所有 `<input type="file">`、`<input type="checkbox">`、`<input type="radio">` 維持原本寫法

## 8. 格式化與前端 build

- [ ] 8.1 若有 .php 變更，執行 `vendor/bin/pint --dirty --format agent`（本次主要動 Blade/CSS，Pint 可能為 no-op）
- [ ] 8.2 執行 `npm run build`（或請使用者執行 `npm run dev` / `composer run dev`）

## 9. 目視驗證

- [ ] 9.1 開啟 `/admin/addons/create`，focus 各文字欄位與 select，確認文字與藍色邊框有清楚間距
- [ ] 9.2 開啟 `/admin/conferences/create`，重複 9.1 驗證
- [ ] 9.3 開啟 `/admin/users/create`、`/admin/grades/create`、`/admin/roles/create`，重複 9.1 驗證
- [ ] 9.4 開啟 `/admin/shops/{shop}/edit`，重複 9.1 驗證
- [ ] 9.5 開啟 `/admin/bills/create`，重複 9.1 驗證
- [ ] 9.6 開啟 `/admin/addons`、`/admin/conferences`、`/admin/bills`、`/admin/shops` 的 filter 列驗證寬度未被拉成 `w-full`
- [ ] 9.7 開啟 `/login`、`/forgot-password`、`/reset-password` 驗證
- [ ] 9.8 開啟含 `<x-searchable-select>` 的頁面驗證搜尋 input 視覺對齊
- [ ] 9.9 比對驗證後欄位視覺與 `<x-password-input>` 的呼吸感一致

## 10. 自動化測試

- [ ] 10.1 執行 `docker compose exec backend-api php artisan test --compact` 確認既有測試全綠