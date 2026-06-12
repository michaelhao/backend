# design — retrofit-bill-management-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit（`3fc327a`、`6a17210`、`a9c8f73`、`0f046ca`、`47811a8`、`17508e1`、`5380364`、`f4e923b`），本 change 只產出 spec 文件、不寫程式。以下記錄已實作架構背後的關鍵決策，作為 spec Requirements 的依據。

## Goals / Non-Goals

**Goals:**
- 一份 `bill-management` capability spec 完整描述帳單建立、計算、列表、明細、報價單、編輯帳務、銷帳、付款安裝與未來生效排程的可觀察行為
- 每個 Scenario 對應一個既有測試案例（tests/Feature/Bill 47 案例 + BillCalculationTest 9 案例），spec 可被測試驗證
- 記錄「刻意不做」的設計（無商店代碼搜尋、無獨立 pay 端點、前端把關版本內含 addon、reason 欄位保留）與架構假設（單 Worker 排程），避免後人誤判為缺漏

**Non-Goals:**
- 不改任何程式碼、不新增測試
- 不規範 `ShopAddonSyncService` 的同步內部規則（屬 addons 範疇，本 spec 僅規範「grade 安裝觸發同步」這個交界）
- 不規範報價單 PDF 的視覺版面細節（僅規範內容範圍與檔名）

## Decisions

1. **金額一律伺服端重算，不信任前端**：`StoreBillRequest` 只收 identifiers 與排程輸入（grade_id/addon_id/start_at/total_months/quantity），單價取自 DB 定價、總價由 `BillCalculationService` 重算；前端 calculate AJAX 僅供顯示預覽。addon 的 `total_price = 單份期間金額 × quantity`（2026-06-13 review 修正 `6a17210`，修掉 Quota addon 少收費）。
2. **付款時 start_at ≤ 今日立即安裝、僅未來寫 bills_future_effect**：原實作 `===` 今日才安裝，過去日期明細落入次日排程造成生效延遲（review 修正 `3fc327a`）。
3. **invalid 為終態**：銷帳全作廢或人工設 invalid 後，MUST NOT 轉回任何其他狀態（review 修正 `a9c8f73`）；paid 亦不可降轉（原有行為）。狀態流轉規則集中在 `UpdateBillRequest::withValidator()`。
4. **付款鎖為 domain exception**：`Cache::lock("bill_pay_{id}", 10)` 取不到鎖時 Service 拋 `BillPaymentLockedException`（自帶 `render()` 回 429 JSON），Service 層不再 `abort()` 觸碰 HTTP（review 重構 `17508e1`）。
5. **重複 addon 後端把關、版本內含 addon 前端把關**：同一帳單內同 addon_id 兩列會造成 `shops_addons` updateOrCreate 互蓋，屬資料完整性問題 → 後端 422（`0f046ca`）。「版本已包含的 addon 不可購買」屬 UX 引導，誤買不破壞資料（安裝為 upsert）→ 維持前端把關，後端不驗證（裁示）。
6. **帳單編號「3 候選 + DB unique 兜底」**：一次產 3 個 `b{Ymd}{His}{8位亂數}` 候選、單查詢排除已存在者，全撞時拋 RuntimeException 由 DB unique index 兜底。與舊 spec「碰撞時重試 3 次」字面不同，為等價的既有實作，照實記載、不改碼（裁示）。
7. **無獨立 pay 端點**：付款一律走編輯帳務 Modal `PATCH /bills/{id}`（`Bill.pay` 權限）將 payment_status 設為 paid 觸發安裝流程。舊 spec 殘留的 `POST /bills/{id}/pay` 字樣為筆誤，MUST NOT 另開端點。
8. **商店搜尋僅支援 ID 與名稱**：shops 表無 code 欄位，舊 spec UI 文案「輸入商店 ID、代碼或名稱」中的「代碼」不存在 → MUST NOT 宣稱支援代碼搜尋（照實記載）。
9. **安裝冪等與並發保護**：`installDetail()` 以 `applied_at IS NOT NULL` 跳過（排程重跑、手動補跑皆安全）；更新 shop 前 `lockForUpdate()`（經 `ShopRepository::getByIdForUpdate()`）防止排程與手動付款互蓋 `expired_at`。
10. **排程單 Worker 假設**：`bills:process-future-effects` 每日 00:05，逐筆獨立 transaction、失敗 Log 後續行（`execute_at ASC, id ASC` 保序）；多伺服器部署需改 Queue + SKIP LOCKED，列為架構假設而非現行需求。
11. **payment_method 建單必填、DB nullable**：歷史資料與未來「付款時才補」彈性保留 DB nullable，但現行建單流程必填（照實記載）。`bills_status_logs.reason` 欄位保留、現行無寫入路徑。
12. **分層**：Controller 僅驗證/調用/回應；Service 不碰 HTTP 與 Eloquent；所有查詢經 Repository（`f4e923b`）；label/badge 集中於 `BillPaymentStatus::label()/badgeClass()`、`BillDetailType::label()`（`5380364`）。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → 每個 Scenario 綁定既有測試；行為變更時測試先失敗，提醒同步更新 spec
- [帳單編號理論上可三連撞] → DB unique index 兜底，內部後台流量下機率可忽略
- [版本內含 addon 僅前端把關] → 接受（裁示）：誤買不破壞資料，僅多收費可人工銷帳
- [單 Worker 排程假設] → 記載於 spec；部署形態改變時須先改造為 Queue Job
- [FormRequest 內少量 Eloquent 驗證查詢] → 接受：驗證屬 HTTP 邊界，非業務邏輯
