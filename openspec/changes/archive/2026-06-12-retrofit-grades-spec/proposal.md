# retrofit-grades-spec

## Why

等級管理（grades CRU + 權重系統 + 狀態切換）歷經多階段演進——原始實作計畫 [spec/5-grades-system.md](../../spec/5-grades-system.md)、權重系統 [spec/11-grade-hierarchy.md](../../spec/11-grade-hierarchy.md)、路由改手動 id 查詢（spec/7 的 grades 部分），以及 2026-06-12 的設計 review 修正（commit `934c2c1` 分層下沉、`9129b44` 文案統一 + checkWeight/找不到 id 測試、`16890c1` 移除 weight default、`e92d71f` 驗證邊界測試）——但 `openspec/specs/` 至今沒有 grade-management capability，且舊版 spec 檔的部分內容已與實作不符（toggleStatus 已改 JSON + axios、路由已改 `{id}`、checkWeight 多了 weight<1 guard）。

本 change 為**文件回填**：將舊版 spec 與設計 review 修正後的實際行為合併為一份反映現狀的 `grade-management` capability spec，並汰除被取代的舊 spec 檔。

## What Changes

- 新增 `grade-management` capability spec，涵蓋現狀行為：
    - 版本列表（依 weight 降序）、新增/編輯（code/name/price/weight/status 驗證規則、unique ignore 自身）
    - 找不到 id 的 redirect-with-flash 行為（edit/update）與 422 JSON（toggle），`{id}` 手動查詢為刻意設計
    - 狀態切換（PATCH → JSON、axios + 確認 modal、Active↔Inactive）
    - checkWeight 即時權重檢查端點（duplicate / exclude_id 豁免 / weight<1 guard）
    - 停用版本「只擋新指派、保留既有引用」的跨功能規則（Shop / Bill / Addon 三個 FormRequest）
    - 刻意不做的設計：無刪除功能（參照穩定性）、權限模組無 delete action
- 刪除 `spec/5-grades-system.md` 與 `spec/11-grade-hierarchy.md`（由本 spec 取代）；保留 `spec/7-edit-route-id.md` 與 `spec/10-fix-form-post-axios.md`（仍涵蓋 users/shops/addons 等未回填功能）
- **無程式變更**：對應實作已在 commit `934c2c1`、`9129b44`、`16890c1`、`e92d71f` 完成並通過 GradeCruTest（30 passed）

## Capabilities

### New Capabilities
- `grade-management`: 版本 CRU、權重排序與唯一性、狀態切換及停用後引用規則的完整行為規格（內部後台、非公網暴露的威脅模型）

### Modified Capabilities
<!-- 無。 -->

## Impact

- **程式碼**：無變更（純文件）。spec 描述的現狀實作位於 `app/Http/Controllers/GradeController.php`、`app/Services/GradeService.php`、`app/Repositories/GradeRepository.php`、`app/Http/Requests/GradeRequest.php`、`app/Enums/GradeStatus.php`、`app/Models/Grade.php`、`database/seeders/GradeSeeder.php`、`resources/js/grades/`（form.js / index.js）
- **文件**：新增 `openspec/specs/grade-management/spec.md`（archive 後）；刪除 `spec/5-grades-system.md`、`spec/11-grade-hierarchy.md`
- **測試**：無變更。spec 的 Scenario 與既有測試一一對應（`tests/Feature/GradeCruTest.php` 30 案例；停用引用規則對應 `ShopRuTest` / `Bill/BillStoreTest` / `AddonCrudTest`）
