# retrofit-users-spec

## Why

使用者管理（User CRUD）歷經多階段演進——原始實作計畫 [spec/4-user-system.md](../../spec/4-user-system.md)、路由改手動 id 查詢（spec/7 的 users 部分）、安全審查補強 [spec/16-users-security-review.md](../../spec/16-users-security-review.md)（自我角色保護、密碼政策 min:12），以及 2026-06-12 的設計 review 修正（commit `898c597` 分層下沉 + 自我保護規則移 Service、`4708b1a` 刪除行為補強、`f65d773` 密碼政策集中、`30c45d8` 補測試缺口）——但 `openspec/specs/` 至今沒有 user-management capability，且舊版 spec 檔的部分內容已與實作不符（password 已從 min:8 改 min:12 + 複雜度、destroy 已改 JSON + axios modal、路由已改 `{id}`、新增刪除時的 sessions 清理與帳單業務參照檢查）。

本 change 為**文件回填**：將舊版 spec 與設計 review 修正後的實際行為合併為一份反映現狀的 `user-management` capability spec，並汰除被取代的舊 spec 檔。

## What Changes

- 新增 `user-management` capability spec，涵蓋現狀行為：
    - 使用者列表（with role、建立時間新→舊）、新增/編輯（name/email/password/role_id 驗證規則、unique ignore 自身、密碼留空不改）
    - 密碼強度政策（`Password::defaults()`：min:12 + 大小寫 + 數字 + 符號；MUST NOT 用 HIBP `uncompromised()`——正式環境無對外網路）
    - 找不到 id 的 redirect-with-flash 行為（edit/update）與 404 JSON（destroy），`{id}` 手動查詢為刻意設計
    - 刪除（DELETE → JSON、axios + 確認 modal、刪除後清除 sessions、被 `bills.shop_sales_id` 參照為帳單業務時拒絕）
    - 自我保護規則（不可刪除自己、不可修改自己的角色；自己的 name/email/password 仍可改）
    - 刻意不做的設計：無自助註冊、無 email 驗證流程（內部後台、帳號僅由管理員建立）
- 刪除 `spec/4-user-system.md` 與 `spec/16-users-security-review.md`（由本 spec 取代；16 的 auth 項目 P1-3/P2-2/P2-5 已由 `openspec/specs/auth/spec.md` 涵蓋、P2-6 為一次性清理的歷史紀錄）；保留 `spec/7-edit-route-id.md`（仍涵蓋 shops 等未回填功能）
- **無程式變更**：對應實作已在 commit `898c597`、`4708b1a`、`f65d773`、`30c45d8` 完成並通過 UserCrudTest（26 passed）

## Capabilities

### New Capabilities
- `user-management`: 使用者 CRUD、密碼政策、自我保護與刪除參照檢查的完整行為規格（內部後台、非公網暴露的威脅模型）

### Modified Capabilities
<!-- 無。 -->

## Impact

- **程式碼**：無變更（純文件）。spec 描述的現狀實作位於 `app/Http/Controllers/UserController.php`、`app/Services/UserService.php`、`app/Repositories/UserRepository.php`、`app/Repositories/BillRepository.php`（existsByShopSalesUserId）、`app/Http/Requests/StoreUserRequest.php`、`app/Http/Requests/UpdateUserRequest.php`、`app/Exceptions/UserOperationException.php`、`app/Providers/AppServiceProvider.php`（Password::defaults）、`resources/views/admin/users/`、`resources/js/users/index.js`
- **文件**：新增 `openspec/specs/user-management/spec.md`（archive 後）；刪除 `spec/4-user-system.md`、`spec/16-users-security-review.md`
- **測試**：無變更。spec 的 Scenario 與既有測試一一對應（`tests/Feature/UserCrudTest.php` 26 案例）
