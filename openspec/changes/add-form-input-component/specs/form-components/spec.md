## ADDED Requirements

### Requirement: `.form-control` 全域 CSS class 為表單元件單一樣式來源

系統 SHALL 在 `resources/css/components/form.css` 定義 `.form-control` named class，並由 `resources/css/app.css` `@import` 載入。

`.form-control` SHALL：

- 透過 `@apply` 包含：`rounded-lg border-gray-300 shadow-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500 text-sm`。
- 定義在 `@layer components` 內，確保 caller 傳入的 utility class（如 `w-full`、`w-48`）能正確覆寫同類屬性。
- **NOT** 包含 `w-full` 或任何 width utility。寬度由 caller 自行決定。
- **NOT** 包含 focus ring 或 border 顏色以外的色彩變化（不順手統一顏色主題）。

`<x-form-input>`、`<x-form-select>`、`<x-password-input>`、`<x-searchable-select>` 內部 SHALL 顯式套用 `.form-control` 作為基底 class。

#### Scenario: app.css 載入 form.css
- **WHEN** 開啟 `resources/css/app.css`
- **THEN** 存在 `@import './components/form.css';` 一行

#### Scenario: .form-control 不含 w-full
- **WHEN** 開啟 `resources/css/components/form.css`
- **THEN** `.form-control` 的 `@apply` 字串內不出現 `w-full` 或其他 `w-*` utility

### Requirement: `<x-form-input>` 元件提供文字類 input 標準樣式

系統 SHALL 提供 `resources/views/components/form-input.blade.php` Blade 元件，封裝後台與 auth 頁面共用的文字類 `<input>` 樣式。

元件 SHALL：

- 接受 props：`name`（必填）、`id`（選填，預設等於 `name`）、`type`（選填，預設 `text`）、`value`（選填，預設 `null`）。
- 透過 `$attributes->merge(['class' => 'form-control'])` 套用基底樣式。
- 永遠輸出 `value="{{ $value }}"` 屬性（不對 null 做特殊處理）。
- 透過 attribute bag 透傳任意 HTML 屬性（如 `placeholder`、`required`、`min`、`step`、`pattern`、`autofocus`、`autocomplete` 等）。

元件支援的 type 範圍 SHALL 為：`text` / `number` / `email` / `url` / `tel` / `date` / `search`。

元件 **NOT** 用於 `type="password"` 場景；password 場景一律使用 `<x-password-input>`。`<x-form-input>` 與 `<x-password-input>` 職責分明、不重疊。

#### Scenario: 呼叫端只傳 name
- **WHEN** Blade 寫 `<x-form-input name="email" />`
- **THEN** 渲染結果為一個 `type="text"`、`name="email"`、`id="email"` 的 `<input>`
- **AND** class 屬性包含 `form-control`

#### Scenario: 呼叫端指定 type 與 value
- **WHEN** Blade 寫 `<x-form-input type="number" name="price" :value="old('price', $addon->price ?? '')" />`
- **THEN** 渲染結果為 `type="number"` 的 `<input>`
- **AND** `value` 屬性等於 `old('price', $addon->price ?? '')` 的值

#### Scenario: 呼叫端透傳額外 HTML 屬性
- **WHEN** Blade 寫 `<x-form-input name="quantity" type="number" min="0" required placeholder="輸入數量" />`
- **THEN** 渲染結果包含 `min="0"`、`required`、`placeholder="輸入數量"` 屬性

#### Scenario: 呼叫端附加寬度 class
- **WHEN** Blade 寫 `<x-form-input name="keyword" class="w-48" />`
- **THEN** 渲染結果的 class 屬性同時包含 `form-control` 與 `w-48`
- **AND** 實際渲染寬度為 `w-48`（不被 `.form-control` 內任何 width 干擾）

#### Scenario: 呼叫端指定 id 不同於 name
- **WHEN** Blade 寫 `<x-form-input name="conferences[0][title]" id="conference_title_0" />`
- **THEN** 渲染結果的 `id` 屬性為 `conference_title_0`、`name` 屬性為 `conferences[0][title]`

### Requirement: `<x-form-select>` 元件提供 select 標準樣式

系統 SHALL 提供 `resources/views/components/form-select.blade.php` Blade 元件，封裝後台與 auth 頁面共用的 `<select>` 樣式。

元件 SHALL：

- 接受 props：`name`（必填）、`id`（選填，預設等於 `name`）。
- 透過 `$attributes->merge(['class' => 'form-control'])` 套用基底樣式。
- 為純 slot wrapper：渲染 `<select ...>{{ $slot }}</select>`，由 caller 在 slot 內自行寫 `<option>` 與 `selected` 判斷。
- 透過 attribute bag 透傳任意 HTML 屬性。

#### Scenario: 純 slot wrapper 渲染
- **WHEN** Blade 寫：
  ```blade
  <x-form-select name="role_id" class="w-full">
      <option value="">請選擇角色</option>
      @foreach ($roles as $role)
          <option value="{{ $role->id }}" {{ old('role_id', $user->role_id ?? '') == $role->id ? 'selected' : '' }}>
              {{ $role->name }}
          </option>
      @endforeach
  </x-form-select>
  ```
- **THEN** 渲染結果為 `<select name="role_id" id="role_id" class="form-control w-full">`，內含 caller 提供的所有 option（含 selected 屬性）

### Requirement: `<x-password-input>` 與 `<x-searchable-select>` 內部改用 `.form-control`

`resources/views/components/password-input.blade.php` SHALL 將其 `<input type="password">` 的預設 class 改為 `form-control w-full pr-10`（保留 `w-full` 與 `pr-10`，後者給切換按鈕留空間）。

`resources/views/components/searchable-select.blade.php` SHALL 將其搜尋 `<input>` 的預設 class 改為 `form-control w-full ss-input`（保留 `.ss-input` 給 Alpine/JS 綁定、保留 `w-full`）。

兩元件 SHALL NOT 再內含完整 hard-coded class 字串（`rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm` 等）；這些樣式集中於 `.form-control`。

#### Scenario: password-input 視覺呼吸感與 form-input 對齊
- **WHEN** 使用者開啟含 `<x-password-input>` 的頁面（如 `/login`、`/reset-password`）
- **THEN** focus 狀態下文字與藍色邊框之間的內距與 `<x-form-input>` 一致

#### Scenario: searchable-select 內部 input 視覺呼吸感與 form-input 對齊
- **WHEN** 使用者開啟含 `<x-searchable-select>` 的頁面
- **THEN** focus 搜尋框時，文字與邊框內距與其他 form input 一致

### Requirement: 既有文字 input 與 select 全面遷移至元件

下列 Blade 檔的原生 `<input>`（type 為 text / number / email / url / tel / date / search）與 `<select>` **SHALL** 改用 `<x-form-input>` / `<x-form-select>` 元件，不再直接寫原生標籤配 `border-gray-300 ... focus:border-blue-500` 字串樣式：

- `resources/views/admin/addons/_form.blade.php`
- `resources/views/admin/conferences/_form.blade.php`
- `resources/views/admin/users/_form.blade.php`
- `resources/views/admin/grades/_form.blade.php`
- `resources/views/admin/roles/_form.blade.php`
- `resources/views/admin/bills/create.blade.php`
- `resources/views/admin/shops/edit.blade.php`
- `resources/views/admin/addons/index.blade.php`
- `resources/views/admin/conferences/index.blade.php`
- `resources/views/admin/bills/index.blade.php`
- `resources/views/admin/shops/index.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`

下列元素 **SHALL NOT** 被本次遷移觸及：`<textarea>`、`<input type="file">`、`<input type="checkbox">`、`<input type="radio">`。

寬度規則：form 欄位 caller SHALL 傳 `class="w-full"`，filter 列 caller SHALL 傳對應的窄寬 class（如 `class="w-48"`）。

#### Scenario: 遷移完成後 grep 驗收
- **WHEN** 對 repo 執行 `grep -rln 'border-gray-300' resources/views --exclude-dir=components`
- **THEN** 命中數為 0，或剩餘者皆為 `type="file"` / `type="checkbox"` / `type="radio"` 等本次不在範圍的 input

#### Scenario: components 目錄 grep 驗收
- **WHEN** 對 repo 執行 `grep -rln 'border-gray-300' resources/views/components`
- **THEN** 命中數為 0（password-input / searchable-select 已改吃 `.form-control`）

#### Scenario: 表單頁面 focus 視覺
- **WHEN** 使用者開啟任一遷移後的表單頁面（如 `/admin/addons/create`、`/login`）並 focus 任一文字類欄位或 select
- **THEN** 輸入文字（或 select 顯示文字）與藍色邊框之間具備可見的水平內距
- **AND** 視覺呼吸感與既有 `<x-password-input>` 元件對齊

#### Scenario: filter 列窄寬保留
- **WHEN** 使用者開啟遷移後的 admin index 頁（如 `/admin/addons`、`/admin/conferences`）
- **THEN** filter 列的輸入框寬度為 caller 指定的窄寬（不被 `.form-control` 強制拉成 `w-full`）

#### Scenario: 非範圍內元素視覺不變
- **WHEN** 使用者開啟遷移後的頁面（如 `/admin/addons/_form`、`/admin/users/_form`）
- **THEN** 頁面內的 `<input type="checkbox">`、`<input type="file">` 視覺與遷移前一致