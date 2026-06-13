# tasks — retrofit-delete-confirmation-spec

文件回填 + 小幅一致性修正。程式修正已於 commit `65068af`、`98b84e5` 完成。

## 1. 程式修正（已完成於 65068af / 98b84e5）

- [x] 1.1 Role / Addon destroy not-found 422 → 404，對齊 User（`65068af`）
- [x] 1.2 PermissionTest role not-found 改斷言 404 並更名；AddonCrudTest 新增 not-found 404 測試（`65068af`）
- [x] 1.3 抽 `resources/js/utils/deleteModal.js`，三個 index.js 改 import（`98b84e5`）
- [x] 1.4 成功 flash 改用 server message；modal 補 Esc / 點背景關閉（`98b84e5`）
- [x] 1.5 pint 通過；相關測試綠（destroy/delete filter 5 passed）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（回填動機與範圍）
- [x] 2.2 撰寫 design.md（capability 切分、404 一致化、CSRF、共用 JS 不共用 HTML 等決策）
- [x] 2.3 撰寫 specs/delete-confirmation/spec.md（ADDED Requirements + Scenario，link 三個 module spec）
- [x] 2.4 更新 role-management / addon-management spec 的 not-found scenario 為 404
- [x] 2.5 刪除舊版 `spec/10-fix-form-post-axios.md`
- [ ] 2.6 `openspec validate retrofit-delete-confirmation-spec --strict` 通過後 archive，合併至 `openspec/specs/delete-confirmation/spec.md`

## 3. HTML 文件

- [ ] 3.1 從 spec.md 產生 `docs/delete-confirmation-spec.html`（單檔自包含、零外部資源，範本 `docs/auth-spec.html`）
- [ ] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [ ] 3.3 全套件測試確認無回歸
