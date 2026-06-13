# tasks — retrofit-addons-spec

本 change 為文件回填：程式實作已於 commit `c0d5ae9`（fix 列表下架篩選 status=0 falsy）、`ddabe18`（test 補同步批次 then/catch 解除 syncing）、`a68c8d3`（feat 停用版本 checkbox disabled）、`8636db0`（refactor 分層合規）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於上列 commits）

- [x] 1.1 列表 `status` 篩選改 `filled()` 判斷，修正下架（status=0）失效；補 status/keyword/type/grade_id/per_page 篩選測試（c0d5ae9）
- [x] 1.2 補 `Bus::batch` then（成功）/ catch（失敗）皆重置 `addons.syncing=Done` 的測試，失敗路徑確認寫 error log（ddabe18）
- [x] 1.3 建立 / 編輯表單對「未關聯且非 Active」版本 checkbox 加 `disabled` 並標「（已停用）」，已關聯停用版本維持可勾；補 HTTP 斷言測試（a68c8d3）
- [x] 1.4 Controller 三處 `Addon::find` 收斂為 `AddonService::findAddonById()`；`getIndexData(Request)` 改 `(array $filters, int $perPage)`；Service 內 Grade 查詢走 `GradeRepository::getByIds`/`getAll`、syncing 更新走 `AddonRepository::setSyncingById`（8636db0）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/addon-management/spec.md（Requirement + Scenario，與既有測試一一對應）
- [x] 2.4 刪除舊版 `spec/9-addons-system.md`、`spec/12-addons-balances.md`
- [x] 2.5 `openspec validate retrofit-addons-spec` 通過後 archive，合併至 `openspec/specs/addon-management/spec.md`

## 3. HTML 文件

- [ ] 3.1 從 spec.md 產生 `docs/addon-management-spec.html`（單檔自包含、零外部資源，範本 `docs/bill-management-spec.html`）
- [ ] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [ ] 3.3 全套件測試確認無回歸
