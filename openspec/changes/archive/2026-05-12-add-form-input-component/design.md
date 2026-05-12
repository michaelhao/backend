## Context

- Tailwind v4 專案，未安裝 `@tailwindcss/forms`。
- [resources/css/app.css](resources/css/app.css) 僅 `@import 'tailwindcss'` + `@source` + 字型主題，無任何全域 form reset。
- 14 個 Blade 檔的文字類 `<input>` 與 `<select>` 共用相同字串樣式（`w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm`），但缺 `px-*` / `py-*`。
- 對照組 [resources/views/components/password-input.blade.php:17](resources/views/components/password-input.blade.php#L17) 顯式 `px-3 py-2`，視覺正常。
- [resources/views/components/searchable-select.blade.php:23](resources/views/components/searchable-select.blade.php#L23) 的搜尋 input 也用同一串樣式、同樣缺 padding。
- 本專案已存在 `<x-permission>`、`<x-searchable-select>`、`<x-password-input>` 等 Blade component 慣例。

## Goals / Non-Goals

**Goals:**
- 解決 focus 時文字貼邊的視覺壓迫，使所有 form control 視覺呼吸感對齊 `<x-password-input>`。
- 把「文字 input / select 標準樣式」收斂到單一 CSS class `.form-control`，避免散落在多個 Blade 元件與 14 個檔案各自複製貼上。
- 讓 `<x-password-input>` / `<x-searchable-select>` 也吃同一個 base，達到 form component 家族一致。
- 為未來擴張（`<x-form-textarea>` 等）預留 `form-components` capability。

**Non-Goals:**
- 不引入 `@tailwindcss/forms` plugin。
- 不修改 focus ring / border 顏色設定（不順手統一）。
- 不處理 `<textarea>`（repo 0 處）、`<input type="file" / checkbox / radio>`。
- 不處理 label / error message 顯示（既有 pattern 都在元件外）。
- 不調整 Tailwind 主題或新增 CSS 變數。

## Decisions

### D1：用「shared CSS named class + Blade 元件」雙層
- **選擇**：建立 `.form-control` 全域 CSS named class（透過 `@apply` 聚合 utility），由 `<x-form-input>`、`<x-form-select>`、`<x-password-input>`、`<x-searchable-select>` 內部 `class="form-control ..."` 顯式套用。
- **替代方案**：
  1. 只在每個 Blade 元件 hard-code class 字串、不抽 CSS。
  2. 用 `@layer base` 為 `input[type="text"], select, ...` 自動加 padding（全域 selector reset）。
- **理由**：方案 1 樣式定義散落在 4 個元件，未來改 base 要動 4 處；方案 2 是 selector-level reset，無差別影響所有 `<input>` / `<select>`（包括第三方插入），副作用不可控。`.form-control` named class 只在顯式宣告處生效，副作用為零、定義單一來源。
- **代價**：呼叫端必須記得寫 `class="form-control"`（但已收進元件內部，14 個 caller 透過 `<x-form-input>` / `<x-form-select>` 間接取得）。

### D2：`.form-control` 預設不含 `w-full`
- **選擇**：`.form-control` 只負責 border / radius / shadow / padding / focus / font-size；`w-full` 由 caller 顯式給。
- **替代方案**：`.form-control` 含 `w-full`，caller 想窄寬時傳 `class="w-48"` 覆寫。
- **理由**：Tailwind v4 utility 出現順序由 build 排序決定，`.form-control` 內的 `w-full` 不一定能被 caller 的 `w-48` 蓋過；風險直接傷害 4 個 index filter 列（要求窄寬）。「不含 width」讓元件責任邊界乾淨（只負責內距/邊框/focus），width 由 caller 自決。
- **代價**：form 欄位 caller 要顯式寫 `class="w-full"`，多一點冗餘但完全無覆寫不確定性。

### D3：用 `$attributes->merge()` 接收 caller 額外屬性
- **選擇**：`$attributes->merge(['class' => 'form-control'])`。
- **替代方案**：`$attributes->class([...])`。
- **理由**：與既有 `<x-password-input>` 同寫法，維持一致。

### D4：元件只暴露 `name` / `id` / `type` / `value` props，其他靠 attribute bag
- **選擇**：`<x-form-input>` 明確 props 限 4 個（`type` 預設 `text`、`value` 預設 `null`），`<x-form-select>` 明確 props 限 2 個（`name` / `id`）。其他屬性透過 attribute bag。
- **替代方案**：把所有可能 attribute 都列為 props、或加 `:options` / `:selected` helper。
- **理由**：避免 props 列表爆炸；attribute bag 自然支援任意 HTML 屬性，caller 寫法接近原生。`<x-form-select>` 為純 slot wrapper，caller 在 slot 內自由寫 `@foreach` + `selected` 判斷，跟既有寫法等價。

### D5：`value` 永遠輸出，不做 null 特殊處理
- **選擇**：元件直接 `value="{{ $value }}"`。
- **替代方案**：`@if (! is_null($value)) value="..." @endif`（避免 `value=""` 影響 `type="date"` 等）。
- **理由**：既有 14 個 caller 100% 用 `value="{{ old('name', $thing->name ?? '') }}"`，`old()` fallback 為 `''` 永遠回字串、永遠不會回 null，特殊處理在實務上不會被觸發；且 HTML5 對 `value=""` 與無 value 屬性的行為一致，特殊處理防的是不存在的問題。簡化元件、消除冗餘抽象。

### D6：新建 `form-components` capability 而非掛在 `admin-layout` 下
- **選擇**：獨立 capability `form-components`。
- **替代方案**：併入 `admin-layout`。
- **理由**：表單元件也用在 auth 頁面（非 admin layout）；獨立 capability 將「視覺主題」與「表單輸入」分離，未來擴增其他表單元件時責任清晰。

### D7：`<x-password-input>` / `<x-searchable-select>` 順手 refactor 吃 `.form-control`
- **選擇**：本次同步把 `<x-password-input>` 預設 class 改為 `form-control w-full pr-10`、`<x-searchable-select>` 搜尋 input class 改為 `form-control w-full ss-input`（保留 `.ss-input` 給 JS 綁定）。
- **替代方案**：本次只動 `<x-form-input>` / `<x-form-select>`，password-input / searchable-select 留待下一個 change refactor。
- **理由**：base 樣式抽出後若 password-input / searchable-select 不吃同一個 class，會變成家族裡兩個叛逆者，未來改 base 要動 3 處而非 1 處；且 searchable-select 的搜尋 input 本身也有相同 padding 問題。一次 refactor 不重複打開檔案。
- **代價**：scope 比原 proposal 大；password-input 不在原 14 檔遷移名單，但動的只有 1 行 class string。

## Risks / Trade-offs

- **Risk**：`@apply` 在 Tailwind v4 仍支援但需放在 `@layer components` 內才能正確管理 specificity；`.form-control` 與 caller 傳入的 utility（如 `w-full`）疊加時，因 utility class 在 source 較晚出現、specificity 通常會贏，但 `@layer components` 與 base utility 的順序需確認 build 後正確（`@layer components` 在 utilities layer 之前，所以 utility 永遠贏）。
  → **Mitigation**：實作後目視驗證 caller 傳的 `w-full` / `w-48` 真的生效；如出現衝突，調整 `@layer` 宣告。

- **Risk**：14 個 Blade 一次大改，視覺有可能在邊緣案例出現微差。
  → **Mitigation**：tasks 中要求逐檔比對 diff、目視驗證 focus 狀態，並執行 grep 驗收命令確保無遺漏。

- **Trade-off**：未統一 focus ring / border 顏色，視覺呼吸感解決但「樣式分歧」仍存在。
  → **接受**：本次只補 padding；後續若要統一可開新 change（屆時 `.form-control` 內一行 `@apply` 即可全家族同步）。

## Migration Plan

1. 新增 `resources/css/components/form.css`，內含 `.form-control` 定義。
2. 修改 `resources/css/app.css` 加 `@import './components/form.css';`。
3. 新增 `resources/views/components/form-input.blade.php` 與 `resources/views/components/form-select.blade.php`。
4. Refactor `resources/views/components/password-input.blade.php` 與 `resources/views/components/searchable-select.blade.php` 改用 `.form-control`。
5. 依固定順序遷移 14 個 Blade 檔（admin form → admin index filter → auth）。
6. `npm run build` 重新編譯前端。
7. 目視驗證代表性頁面 focus 狀態與 `<x-password-input>` 對齊。
8. 全 repo grep 驗收：`grep -rln 'border-gray-300' resources/views --exclude-dir=components` 應 0 命中（或剩下只在 file/checkbox/radio）。
9. Rollback：如出現大規模視覺問題，revert 本 change 的 commit（單一 change 範圍，回滾乾淨）。

## Open Questions

- 無。Grill 階段已收斂：scope、shared base 形式、`.form-control` 命名與位置、width 處理、value null 處理、password-input / searchable-select 是否同步 refactor、form-select API。