# retrofit-shops-spec

## Why

商店管理（shops：列表 / 編輯 / 更新 / 統編認證）的文件仍停留在原始實作計畫 [spec/6-shops-system.md](../../spec/6-shops-system.md)，外加路由改手動 id 查詢（spec/7 的 shops 部分）與遮蔽 helper 改 `App\Support\Mask` 類別（spec/8）兩次演進——但 `openspec/specs/` 至今沒有 shop-management capability，且 2026-06-12 設計 review 後實際行為已與舊 spec 有多處出入（分層下沉、mass assignment 白名單、不存在 id 的 500 修正、certify 404 JSON、伺服端重驗認證、ShopStatus::label() 等，commit `615c876`、`40a5388`、`6c22dc4`、`21c9517`）。

本 change 為**文件回填**：將舊版 spec 與設計 review 修正後的實際行為合併為一份反映現狀的 `shop-management` capability spec，並汰除被取代的舊 spec 檔。

## What Changes

- 新增 `shop-management` capability spec，涵蓋現狀行為：
    - 商店列表（admin/grade eager load、id 升冪、認證詳情 Modal）、四條件交集搜尋（keyword LIKE、grade_id / business_number 精準、is_certified NULL 判斷）、分頁 per_page 白名單 [50,100,150,200] 預設 50、切換每頁筆數保留篩選（含 `is_certified=0`）
    - 編輯表單（商店 / 管理員雙區塊；admin email、統編以 `App\Support\Mask` 遮蔽）、更新驗證規則與欄位白名單（`safe()`，`admin[password]`/`admin[shop_id]` 不可經表單寫入）
    - 管理員 email `encrypted` cast 與應用層解密比對唯一性（刻意設計）；grade 變更僅允許 Active 版本（維持原 grade 例外）並觸發 addon 同步
    - 儲存時伺服端重驗認證：統編變更重打認證 API、company_name 一律採 API/DB 值不信任表單；certify 端點 8 位數字驗證、success/failure JSON、404 JSON
    - 找不到 id 的 redirect-with-flash（edit/update）與 404 JSON（certify），`{id}` 手動查詢為刻意設計
    - 刻意不做的設計：無新增/刪除（405）、`sales_id`/`expired_at` 不在本 UI 編輯（屬 bills 範疇）
    - 已知限制：政府 API 為全站唯一外部呼叫，正式環境無對外連線 → certify 必回 `success:false`（裁示 D：不改程式碼，僅記載）
- 刪除 `spec/6-shops-system.md`（由本 spec 取代）與 `spec/7-edit-route-id.md`（其四個資源 roles/grades/users/shops 至此皆已被 openspec capability specs 涵蓋）；保留 `spec/8-maskstring-support.md`（共用 helper 文件）
- **無程式變更**：對應實作已在 commit `615c876`、`40a5388`、`6c22dc4`、`21c9517` 完成並通過 ShopRuTest（38 passed）

## Capabilities

### New Capabilities
- `shop-management`: 商店列表搜尋分頁、編輯更新、統編認證與伺服端重驗的完整行為規格（內部後台、非公網暴露的威脅模型；正式環境無對外連線）

### Modified Capabilities
<!-- 無。 -->

## Impact

- **程式碼**：無變更（純文件）。spec 描述的現狀實作位於 `app/Http/Controllers/ShopController.php`、`app/Services/ShopService.php`、`app/Services/ShopAddonSyncService.php`（交界）、`app/Repositories/ShopRepository.php`、`app/Repositories/GradeRepository.php`（getAddonIdsForGrade）、`app/Http/Requests/ShopUpdateRequest.php`、`app/Http/Requests/ShopCertifyRequest.php`、`app/Enums/ShopStatus.php`、`app/Support/Mask.php`、`resources/views/admin/shops/`、`resources/js/shops/`
- **文件**：新增 `openspec/specs/shop-management/spec.md`（archive 後）；刪除 `spec/6-shops-system.md`、`spec/7-edit-route-id.md`
- **測試**：無變更。spec 的 Scenario 與既有測試一一對應（`tests/Feature/ShopRuTest.php` 38 案例）
