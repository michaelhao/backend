# design — retrofit-shops-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit（`615c876`、`40a5388`、`6c22dc4`、`21c9517`），本 change 只產出 spec 文件、不寫程式。以下記錄已實作架構背後的關鍵決策，作為 spec Requirements 的依據。

## Goals / Non-Goals

**Goals:**
- 一份 `shop-management` capability spec 完整描述商店列表搜尋分頁、編輯更新、統編認證與伺服端重驗的可觀察行為
- 每個 Scenario 對應一個既有測試案例（ShopRuTest 38 案例），spec 可被測試驗證
- 記錄「刻意不做」的設計（無新增/刪除、`{id}` 手動查詢、encrypted email 應用層唯一性、sales_id/expired_at 不在本 UI）與已知限制（政府 API vs 無對外連線），避免後人誤判為缺漏

**Non-Goals:**
- 不改任何程式碼、不新增測試
- 不規範 ShopAddon 同步的內部規則（屬 addons 範疇，本 spec 僅規範「grade 變更觸發同步」這個交界）
- 不規範 `bills.shop-search` 與 `sales_id`/`expired_at` 的編輯（屬 bills 範疇）

## Decisions

1. **僅 R（列表）與 U（編輯），無 C/D**：商店帳號由商店端系統開立，後台僅管理既有商店；無 store/destroy 路由，POST/DELETE 回 405（spec/6 原始範圍即如此，刻意維持）。
2. **管理員 email 走 `encrypted` cast、唯一性由應用層解密比對**：加密值不可在 DB 層比對，MUST NOT 加 DB unique index；`ShopService::updateShop()` 於寫入前解密逐筆比對（排除自身），衝突拋 `ValidationException('admin.email')`。O(n) 解密為 encrypted cast 的必然取捨，內部後台資料量可接受。
3. **不採 route model binding**：保留 `{id}` + `findShopById()`（經 `ShopRepository::getById()`）+ redirect-with-flash 的 UX（源自 spec/7）。edit/update 找不到導回列表 + error flash；certify 為 fetch JSON 請求，找不到回 404 JSON（原 302 會被 fetch 靜默跟隨後 json() 解析失敗、誤顯示「認證失敗」，2026-06-12 review 修正，commit `40a5388`）。
4. **更新欄位白名單**：shop 欄位走 `$request->safe()->only()`、admin 欄位走 `validated()['admin']`，未在驗證規則內的 `admin[password]`、`admin[shop_id]` 不可能經表單寫入（2026-06-12 review 修正 mass assignment，commit `40a5388`）。
5. **認證資料以伺服端為準**：表單送來的 `admin[company_name]` 不被信任。統編未變更 → company_name 取 DB 現值（不發 API）；變更 → 伺服端重打認證 API，失敗拋 ValidationException、成功以 API 回傳值寫入；清空 → 兩欄一併 null（2026-06-12 review，commit `6c22dc4`）。前端 certify 流程退化為純 UX 預覽。
6. **grade 變更僅允許 Active 版本，維持原 grade 例外**：已停用版本不開放新指派，但商店現有 grade 已停用時允許原值送出（否則表單永遠無法儲存）；grade 變更觸發 `ShopAddonSyncService` 同步商店 addon（交界，內部規則屬 addons）。
7. **狀態顯示集中於 `ShopStatus::label()`**：中文 label 由 enum 提供，view 僅保留 badge CSS class 對應（2026-06-12 review，commit `615c876`）。
8. **政府 API 為全站唯一外部呼叫（已知限制，裁示 D）**：`verifyCertification()` 呼叫 `data.gcis.nat.gov.tw`（timeout 10s、try/catch 包裹）；正式環境無對外連線 → certify 必回 `success:false`、認證資料無法於正式環境經此流程寫入。裁示為不改程式碼、僅記載於 spec——功能在可連外的環境（開發/測試）正常運作，正式環境的替代方案（如離線名錄）留待未來需求。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → 每個 Scenario 綁定既有測試；行為變更時測試先失敗，提醒同步更新 spec
- [admin email 唯一性檢查 O(n) 全表解密] → 接受：encrypted cast 的必然取捨，內部後台 shops_admin 筆數有限
- [正式環境 certify 必定失敗] → 接受（裁示 D）：記載為已知限制，不在本次 scope 內修正
- [grade 變更觸發的 addon 同步細節不在本 spec] → 交界引用 `ShopAddonSyncService`，避免兩處 spec 重複漂移
