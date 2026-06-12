# tasks — retrofit-bill-management-spec

本 change 為文件回填：程式實作已於 commit `3fc327a`（fix 過去 start_at 立即安裝）、`6a17210`（fix addon total_price 乘 quantity）、`a9c8f73`（fix invalid 終態）、`0f046ca`（fix 後端擋重複 addon）、`47811a8`（test 補端點測試缺口）、`17508e1`（refactor 付款鎖 domain exception）、`5380364`（refactor detail() 瘦身 + label 入 Enum）、`f4e923b`（refactor Eloquent 下放 Repository）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於上列 commits）

- [x] 1.1 付款時 `start_at <= 今日` 立即安裝，僅未來日期寫 bills_future_effect（3fc327a）
- [x] 1.2 addon 明細 `total_price = 單份期間金額 × quantity`，與前端顯示及舊 spec 一致（6a17210）
- [x] 1.3 invalid 設為終態：`UpdateBillRequest` 擋 invalid → 其他狀態（a9c8f73）
- [x] 1.4 `StoreBillRequest` 擋同帳單內重複 addon_id（0f046ca）
- [x] 1.5 補列表/權限/明細/報價單/AJAX/銷帳/付款鎖/排程容錯測試，Bill 測試 56 passed（47811a8）
- [x] 1.6 `abort(429)` 改拋 `BillPaymentLockedException`（自帶 render() 回 429 JSON）（17508e1）
- [x] 1.7 `detail()` JSON 組裝移至 `BillService::getDetailData()`；label/badgeClass 入 Enum（5380364）
- [x] 1.8 BillService/BillPaymentService/BillController 所有 Eloquent 查詢下放 Repository；新增 BillDiscountRepository、ShopAddonRepository（f4e923b）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/bill-management/spec.md（Requirement + Scenario，與既有測試一一對應）
- [x] 2.4 刪除舊版 `spec/13-bill-system.md`
- [x] 2.5 `openspec validate retrofit-bill-management-spec` 通過後 archive，合併至 `openspec/specs/bill-management/spec.md`

## 3. HTML 文件

- [x] 3.1 從 spec.md 產生 `docs/bill-management-spec.html`（單檔自包含、零外部資源，範本 `docs/shop-management-spec.html`）
- [x] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [x] 3.3 全套件測試確認無回歸
