# tasks — retrofit-users-spec

本 change 為文件回填：程式實作已於 commit `898c597`（refactor(users): 查詢與自我保護規則下移至 Service/Repository 層）、`4708b1a`（fix(users): 刪除行為補強——404 語意、清除 sessions、帳單業務參照檢查）、`f65d773`（refactor(users): 密碼政策集中至 Password::defaults()）與 `30c45d8`（test(users): 補齊權限、驗證邊界與不存在 id 的測試缺口）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於 898c597 / 4708b1a / f65d773 / 30c45d8）

- [x] 1.1 UserController 三處 `User::find()` 改經 UserService → `UserRepository::getById()`（898c597）
- [x] 1.2 「無法刪除自己」「無法修改自己的角色」移入 UserService，拋 `UserOperationException`（898c597）
- [x] 1.3 destroy 找不到使用者 422 → 404；刪除後清除 sessions；被 `bills.shop_sales_id` 參照時拒絕刪除（4708b1a）
- [x] 1.4 密碼政策集中至 `AppServiceProvider::boot()` 的 `Password::defaults()`，三處 FormRequest 改引用（f65d773）
- [x] 1.5 補未登入導向、Viewer 無權 update/delete、弱密碼、不存在 id、name 邊界測試；UserCrudTest 26 passed（30c45d8）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/user-management/spec.md（Requirement + Scenario，對應既有測試）
- [x] 2.4 刪除舊版 `spec/4-user-system.md` 與 `spec/16-users-security-review.md`
- [x] 2.5 `openspec validate retrofit-users-spec` 通過後 archive，合併至 `openspec/specs/user-management/spec.md`

## 3. HTML 文件

- [ ] 3.1 從 spec.md 產生 `docs/user-management-spec.html`（單檔自包含、零外部資源，範本 `docs/auth-spec.html`）
- [ ] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [ ] 3.3 全套件測試確認無回歸
