# design — retrofit-addons-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit（`c0d5ae9`、`ddabe18`、`a68c8d3`、`8636db0`），本 change 只產出 spec 文件、不寫程式。以下記錄已實作架構背後的關鍵決策，作為 spec Requirements 的依據。來源：舊版 `spec/9-addons-system.md`、`spec/12-addons-balances.md` 與 2026-06-13 設計 review。

## Goals / Non-Goals

**Goals:**
- 一份 `addon-management` capability spec 完整描述附加功能 CRUD、單圖、Grade 關聯與停用規則、Grade-Addon 變動的商店同步、配額餘額的可觀察行為
- 每個 Scenario 盡可能對應一個既有測試案例，spec 可被測試驗證
- 記錄「刻意不做」的設計（Job 不檢查 addon status、購買觸發不在本範疇、餘額不 cascade delete）與其依據

**Non-Goals:**
- 不規範帳單建立 / 付款 / 加購計價（屬 bill-management），僅以 cross-reference 連結購買觸發點
- 不規範 grade CRU 與權重（屬 grade-management），僅規範「停用版本不可被新關聯」的 addon 側規則
- 不規範商店 grade_id 直接變更的入口（屬 shop-management），但其呼叫的 `ShopAddonSyncService::syncForShop` 同步邏輯由本 spec 定義

## Decisions

1. **列表 status 篩選以 `filled()` 判斷**：`status` 合法值含 `0`（下架），`'0'` 為 PHP falsy，原 `when($filters['status'] ?? null)` 會把下架篩選當成未篩選而失效。改 `when(filled(...))` 修正（commit `c0d5ae9`）。`type` / `grade_id` 無 `0` 值故不受影響，維持原寫法。

2. **軟刪除主表 + 物理刪除關聯**：`addons.status = -1` 保留名稱 / 價格歷史（被 bill detail 等引用）；`grades_addons` / `shops_addons` / `addons_image` 於同一交易物理刪除（無歷史保留需求）。圖片實體檔於交易提交後刪除。

3. **編輯「已刪除＝不存在」收斂於 Service**：`AddonService::findAddonById()` 對 `status = -1` 回 `null`，Controller 僅判 `null` 後 redirect/422，不再直接碰 Eloquent 或 Enum（commit `8636db0`，比照 Shop/Grade 慣例）。

4. **Grade-Addon 變動走 Queue 批次 + syncing 旗標**：版本下可能有大量商店，per-shop diff-and-apply 丟入背景 Job；多個受影響版本以 `Bus::batch` 統一追蹤。`addons.syncing` 提供 addon 頁就地顯示「同步中」，免跨頁查 Grade。**批次 then 與 catch 皆重置 syncing=Done**（失敗並記 log），避免失敗時旗標永久卡住；補測試覆蓋兩條路徑（commit `ddabe18`）。

5. **獨立 queue `addon_sync`**：同步 Job 不混入 `default`，以 `--queue=addon_sync` 獨立 worker 消化。舊 spec 草稿寫 `addons`，實作為 `addon_sync`，spec 以實作為準。

6. **同步冪等性來自 per-grade 全集合 diff**：Job 每次取版本當前完整 addon 集合再對單一商店差集套用，重試安全。因此**不需** Job 內 per-addon `status != -1` 檢查（舊 spec 4.冪等性曾提）——addon 軟刪除時 `grades_addons` 已在同交易物理刪除，版本集合天然不含已刪除 addon。

7. **同步差集三類動作**：移除→降級為 `Purchased` 並壓當日 23:59:59（保留商店權益至當日結束）；新增→若已有同 addon 的 `Purchased` 則升級為 `Grade` 並清 `expired_at`，否則純新增；不在目標的獨立 `Purchased` 維持原狀。`syncForShop` 各自包 `DB::transaction` 確保單店原子性、Job 重試不致部分更新。

8. **配額餘額獨立過期**：`shops_addons` 不可重複購買（UNIQUE shop+addon），但 `AddonType::Quota` 可疊加；每次購買在 `shop_addon_balances` 記獨立 `expired_at`，`getAvailableQuantity` 只加總未過期者。餘額**不 cascade delete**，保留歷史。

9. **購買觸發點屬 bill-management**：舊 spec/12「待實作（購買觸發點）」現已由 `BillPaymentService::installAddonDetail` 實作（付款安裝 upsert `shops_addons` source=Purchased、Quota 寫 balance）。本 spec 以刻意設計 Requirement + cross-reference 記載，不重複規範計價。

10. **停用版本 UX 與驗證一致**：`AddonRequest` 擋新關聯停用版本、放行既有關聯；表單 checkbox 對「未關聯且非 Active」加 `disabled` 並標「（已停用）」，避免使用者誤勾後才被擋（commit `a68c8d3`）。

11. **分層比照 role/shop/grade 基準**：Controller 不直呼 Eloquent；`getIndexData` 收 `array $filters, int $perPage`（per_page 白名單正規化在 Controller）；Service 內 Grade 查詢走 `GradeRepository`、syncing 更新走 `AddonRepository::setSyncingById`（commit `8636db0`）。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → 每個 Scenario 綁定既有測試；行為變更時測試先失敗，提醒同步更新 spec
- [同步 Job 失敗後 syncing 被重置為 Done 但商店資料可能部分未同步] → 接受：失敗已記 error log，管理員重新儲存 addon 即重新 dispatch；單店 `syncForShop` 自身為交易原子，不會半套
- [`syncForShop` 對版本下每家商店逐筆交易，萬店時 Job 較久] → 接受：已用獨立 queue 隔離，不阻塞 web request；批次完成才解除 syncing
- [shop_addon_balances 無專屬 FK / cascade] → 刻意設計，保留歷史；清理由業務排程（若有）另行處理，不在本範疇
