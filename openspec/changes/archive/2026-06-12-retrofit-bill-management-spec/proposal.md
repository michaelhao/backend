# retrofit-bill-management-spec

## Why

帳務管理（bills：建立 / 列表 / 明細 Modal / 報價單 / 編輯帳務 / 銷帳 / 付款安裝 / 未來生效排程）的文件仍停留在原始實作計畫 [spec/13-bill-system.md](../../spec/13-bill-system.md)，`openspec/specs/` 至今沒有 bill-management capability。2026-06-13 設計 review 後實際行為已與舊 spec 有多處出入：4 個行為修正（過去 start_at 立即安裝、addon total_price 乘 quantity、invalid 終態、後端擋重複 addon）、3 項分層重構（429 domain exception、detail() 瘦身與 label 入 Enum、Eloquent 全面下放 Repository），commit `3fc327a`、`6a17210`、`a9c8f73`、`0f046ca`、`47811a8`、`17508e1`、`5380364`、`f4e923b`。

本 change 為**文件回填**：將舊版 spec 與設計 review 修正後的實際行為合併為一份反映現狀的 `bill-management` capability spec，並汰除被取代的舊 spec 檔。

## What Changes

- 新增 `bill-management` capability spec，涵蓋現狀行為：
    - 資料模型（bills / bills_details / bills_future_effect / bills_discount / bills_status_logs / shops.sales_id）、帳單編號「3 候選 + DB unique 兜底」產生法（與舊 spec「重試 3 次」描述不同，照實記載）
    - 金額計算：月份補齊規則（0=月底、1 號起算、非 1 號 partial month）、部分月份公式（`Carbon::daysInMonth`，禁止寫死）、各明細各自四捨五入、升級補差額（以版本定價為準）
    - 建立帳單：shop-search / shop-info / calculate 三個 AJAX 端點、伺服端重算金額不信任前端、addon total_price × quantity、同帳單重複 addon 後端 422、待處理帳單警示
    - 列表四條件交集搜尋與分頁保留條件；明細 Modal（含作廢區塊）；報價單 PDF（僅有效明細、檔名格式、WQY MicroHei 字型）
    - 編輯帳務 PATCH：狀態流轉規則（paid 不可降轉、invalid 終態）、status log 審計、付款鎖 `Cache::lock` 429（`BillPaymentLockedException` domain exception）
    - 付款安裝：start_at ≤ 今日立即安裝、> 今日寫 bills_future_effect；applied_at 冪等；shop lockForUpdate；grade 安裝同步 addon、Quota addon 寫 balance
    - 銷帳：僅 pending/unpaid、折抵自動縮減、全作廢轉 invalid + status log
    - 排程 `bills:process-future-effects`（dailyAt 00:05、單筆失敗不中斷、單 Worker 假設）
    - 刻意不做：無商店「代碼」搜尋（shops 無 code 欄位）、無獨立 pay 端點、「版本已含 addon 不可購買」由前端把關、`bills_status_logs.reason` 保留未使用、payment_method 建單必填（DB nullable）
- 刪除 `spec/13-bill-system.md`（由本 spec 取代）
- **無程式變更**：對應實作已在上列 8 個 commit 完成並通過 Bill 測試（56 feature + 9 unit）

## Capabilities

### New Capabilities
- `bill-management`: 帳單建立、列表、明細、報價單、付款安裝、銷帳與未來生效排程的完整行為規格（內部後台、非公網暴露的威脅模型；正式環境無對外連線，本功能無任何外部呼叫）

### Modified Capabilities
<!-- 無。 -->

## Impact

- **程式碼**：無變更（純文件）。spec 描述的現狀實作位於 `app/Http/Controllers/BillController.php`、`app/Services/BillService.php`、`app/Services/BillPaymentService.php`、`app/Services/BillCalculationService.php`、`app/Repositories/Bill*Repository.php`、`app/Repositories/ShopAddonRepository.php`、`app/Http/Requests/{Store,Update,Writeoff}BillRequest.php`、`app/Exceptions/BillPaymentLockedException.php`、`app/Enums/{BillPaymentStatus,BillDetailType}.php`、`app/Console/Commands/ProcessBillFutureEffects.php`
- **文件**：新增 `openspec/specs/bill-management/spec.md`（archive 後）；刪除 `spec/13-bill-system.md`
- **測試**：無變更。spec 的 Scenario 與既有測試一一對應（`tests/Feature/Bill/` 4 檔 47 案例 + `tests/Unit/Bill/BillCalculationTest.php` 9 案例）
