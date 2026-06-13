# tasks — retrofit-maskstring-spec

本 change 為文件回填：核心實作已於 commit `816ad29`（遷入 `App\Support\Mask`）、`186da22`（移除 `MaskHelpers.php`）完成；2026-06-13 review 修正於 `b81e784`、`9f8083a` 完成。對應任務直接標記完成。

## 1. 程式實作（已完成於上列 commits）

- [x] 1.1 遮蔽邏輯遷入 `App\Support\Mask` 靜態類別（`816ad29`）
- [x] 1.2 移除 `app/Helpers/MaskHelpers.php` 全域函式，PHP 端改用 `Mask::`（`186da22`）
- [x] 1.3 補 `tests/Unit/Support/MaskTest.php`，直接驗證 `string()`/`email()` 遮蔽演算法與邊界（`b81e784`）
- [x] 1.4 `resources/js/shops/edit.js` 的 `maskString` 加註解指回 `App\Support\Mask`，標明雙端須同演算法（`9f8083a`）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/string-masking/spec.md（Requirement + Scenario，與 `MaskTest` 一一對應）
- [x] 2.4 刪除舊版 `spec/8-maskstring-support.md`
- [x] 2.5 `openspec validate retrofit-maskstring-spec` 通過後 archive，合併至 `openspec/specs/string-masking/spec.md`

## 3. HTML 文件

- [x] 3.1 從 spec.md 產生 `docs/string-masking-spec.html`（單檔自包含、零外部資源，範本 `docs/auth-spec.html`）（`f707abc`）
- [x] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [x] 3.3 全套件測試確認無回歸
