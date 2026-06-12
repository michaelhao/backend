# tasks — retrofit-shops-spec

本 change 為文件回填：程式實作已於 commit `615c876`（refactor(shops): 分層架構修正——查詢入 Repository、Service 脫離 HTTP、狀態 label 入 enum）、`40a5388`（fix(shops): 修正 mass assignment、不存在 id 500、certify 回應格式等五項行為）、`6c22dc4`（feat(shops): 認證資料改以伺服端為準，儲存時重驗統一編號）與 `21c9517`（test(shops): 補列表搜尋篩選、分頁與未登入導向測試）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於 615c876 / 40a5388 / 6c22dc4 / 21c9517）

- [x] 1.1 Controller `Shop::find()` 改經 ShopService → `ShopRepository::getById()`；`getIndexData()` 脫離 Request；`grades_addons` 裸查詢入 GradeRepository；`ShopStatus::label()`（615c876）
- [x] 1.2 update 改 `safe()`/`validated()` 白名單；ShopUpdateRequest 不存在 id 500 修正；certify 找不到商店改 404 JSON；per-page 表單保留 `is_certified=0`；無 admin 商店防護（40a5388）
- [x] 1.3 儲存時伺服端重驗統編：變更重打 API、未變更取 DB 值、清空雙欄 NULL、company_name 不信任表單（6c22dc4）
- [x] 1.4 補未登入導向、四條件篩選與交集、per_page 白名單與 fallback 測試；ShopRuTest 38 passed（21c9517）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/shop-management/spec.md（Requirement + Scenario，與 ShopRuTest 38 案例一一對應）
- [x] 2.4 刪除舊版 `spec/6-shops-system.md` 與 `spec/7-edit-route-id.md`（roles/grades/users/shops 四資源至此皆有 capability spec）
- [x] 2.5 `openspec validate retrofit-shops-spec` 通過後 archive，合併至 `openspec/specs/shop-management/spec.md`

## 3. HTML 文件

- [ ] 3.1 從 spec.md 產生 `docs/shop-management-spec.html`（單檔自包含、零外部資源，範本 `docs/user-management-spec.html`）
- [ ] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [ ] 3.3 全套件測試確認無回歸
