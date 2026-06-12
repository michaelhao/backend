# tasks — retrofit-grades-spec

本 change 為文件回填：程式實作已於 commit `934c2c1`（refactor(grades): Controller 查詢與 checkWeight 邏輯下沉至 Service/Repository）、`9129b44`（fix(grades): 統一找不到訊息並補 checkWeight/找不到 id 測試）、`16890c1`（fix(grades): 移除 weight 與 unique index 矛盾的 default(0)）與 `e92d71f`（test(grades): 補驗證邊界與列表排序測試）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於 934c2c1 / 9129b44 / 16890c1 / e92d71f）

- [x] 1.1 GradeController 三處 `Grade::find()` 改經 GradeService → `GradeRepository::getById()`（934c2c1）
- [x] 1.2 checkWeight payload 組裝與 weight<1 guard 下沉為 `GradeService::checkWeightConflict()`（934c2c1）
- [x] 1.3 edit/update 錯誤文案「找不到該方案」統一為「找不到該版本」（9129b44）
- [x] 1.4 補 checkWeight 5 案例與找不到 id 3 案例測試（9129b44）
- [x] 1.5 移除 grades.weight 與 unique index 矛盾的 default(0)（16890c1）
- [x] 1.6 補驗證邊界（weight required / min:1、price 負數）與列表降序測試；GradeCruTest 30 passed（e92d71f）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/grade-management/spec.md（Requirement + Scenario，對應既有測試）
- [x] 2.4 刪除舊版 `spec/5-grades-system.md` 與 `spec/11-grade-hierarchy.md`
- [x] 2.5 `openspec validate retrofit-grades-spec` 通過後 archive，合併至 `openspec/specs/grade-management/spec.md`

## 3. HTML 文件

- [ ] 3.1 從 spec.md 產生 `docs/grade-management-spec.html`（單檔自包含、零外部資源，範本 `docs/auth-spec.html`）
- [ ] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [ ] 3.3 全套件測試確認無回歸
