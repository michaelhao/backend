# retrofit-addons-spec

## Why

附加功能管理（addons：CRUD / 單圖上傳 / Grade 關聯與停用規則 / Grade-Addon 變動的商店同步 Job / 配額餘額）的文件仍停留在原始實作計畫 [spec/9-addons-system.md](../../../spec/9-addons-system.md) 與 [spec/12-addons-balances.md](../../../spec/12-addons-balances.md)，`openspec/specs/` 至今沒有 addon-management capability。2026-06-13 設計 review 後實際行為已與舊 spec 有多處出入：1 個行為修正（列表「下架」篩選 `status=0` falsy 失效）、1 項 UX 修正（停用版本 checkbox disabled）、1 項測試補強（同步批次 then/catch 解除 syncing）、3 項分層重構（Controller 去 Eloquent、Service 去 Request、Service 去 Eloquent），commit `c0d5ae9`、`ddabe18`、`a68c8d3`、`8636db0`。

本 change 為**文件回填**：將舊版 spec 與設計 review 修正後的實際行為合併為一份反映現狀的 `addon-management` capability spec，並汰除被取代的舊 spec 檔。

## What Changes

- 新增 `addon-management` capability spec，涵蓋現狀行為：
    - 資料模型（addons 軟刪 `status=-1` 與 `syncing` 旗標、addons_image 單圖 UNIQUE、grades_addons、shops_addons 的 `source`/`expired_at`、shop_addon_balances 獨立過期）折入各行為 Requirement
    - 列表：分頁排除已刪除、`per_page` 白名單 `[50,100,150,200]`、四條件篩選（keyword/type/status/grade_id）；**修正後 `status=0`（下架）以 `filled()` 判斷，不再被 truthiness 誤判為未篩選**
    - 建立 / 編輯 / 刪除：FormRequest 驗證（status 拒 -1）、單一交易、軟刪除同交易物理刪除 pivot、編輯解析「已刪除＝不存在」
    - 圖片：jpg/png ≤ 5MB、改名 `{id}-img-{ts}.{ext}`、先存新再刪舊、remove_image、asset() 生成 URL
    - Grade 關聯與停用版本規則：新關聯須 Active、已關聯停用版本允許保留、**表單 checkbox disabled 標示「（已停用）」**
    - Grade-Addon 變動觸發商店同步：`Bus::batch` + `SyncShopAddonsForGrade`、獨立 queue `addon_sync`（與舊 spec 寫的 `addons` 不同，照實記載）、`syncing` 旗標、**then/catch 一律重置 syncing（失敗並記 log）**、`queue:prune-batches --hours=48` 清理
    - 商店 addon 同步差集規則（`ShopAddonSyncService::syncForShop`：降級 / 升級 / 純新增 / 獨立加購維持）
    - 配額餘額獨立過期與 `getAvailableQuantity`（未過期加總）
    - 刻意設計：同步 Job 冪等性來自 per-grade 全集合 diff（MUST NOT 依賴 per-addon status 檢查）、購買觸發點不在本範疇（由 bill-management 付款安裝實作）、shop_addon_balances 不 cascade delete
- 刪除 `spec/9-addons-system.md`、`spec/12-addons-balances.md`（由本 spec 取代）
- **無新增程式變更於文件階段**：對應實作已在上列 4 個 commit 完成並通過 Addon 測試

## Capabilities

### New Capabilities
- `addon-management`: 附加功能 CRUD、單圖上傳、Grade 關聯與停用規則、Grade-Addon 變動的商店同步（Queue 批次）與配額餘額的完整行為規格（內部後台、非公網暴露的威脅模型；正式環境無對外連線，本功能無任何外部呼叫）

### Modified Capabilities
<!-- 無。購買觸發行為屬 bill-management，本 change 僅以 cross-reference 連結，不修改其 spec。 -->

## Impact

- **程式碼**：spec 描述的現狀實作位於 `app/Http/Controllers/AddonController.php`、`app/Services/AddonService.php`、`app/Services/ShopAddonSyncService.php`、`app/Repositories/{Addon,ShopAddon,ShopAddonBalance,Grade}Repository.php`、`app/Jobs/SyncShopAddonsForGrade.php`、`app/Http/Requests/AddonRequest.php`、`app/Models/{Addon,AddonImage,ShopAddon,ShopAddonBalance}.php`、`app/Enums/{AddonStatus,AddonSyncing,AddonType,ShopAddonSource,ShopAddonStatus}.php`、`resources/views/admin/addons/`
- **文件**：新增 `openspec/specs/addon-management/spec.md`（archive 後）；刪除 `spec/9-addons-system.md`、`spec/12-addons-balances.md`
- **測試**：spec 的 Scenario 與既有測試一一對應（`tests/Feature/AddonCrudTest.php`、`tests/Feature/ShopAddon/`：ShopAddonSyncTest、SyncShopAddonsForGradeTest、ShopAddonBalanceTest）
