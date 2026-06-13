# retrofit-delete-confirmation-spec

## Why

舊版 [spec/10-fix-form-post-axios.md](../../spec/10-fix-form-post-axios.md) 描述一個跨模組功能：把 roles / users / addons 列表的刪除，從「inline `<form>` POST + 瀏覽器原生 `confirm()`」改為「axios DELETE + 自訂確認 modal + 動態 flash + 移除 DOM 列」（實作於 commit `b824d18`，security fix 隨後）。

三個模組 `destroy()` 的**後端**業務行為（JSON 回應、業務錯誤碼、軟刪除、pivot 清理、權限）已分別回填至 `role-management` / `user-management` / `addon-management` capability。但 `spec/10` 的**本體**——三頁共用的**前端刪除互動契約**（按鈕 data attribute 約定、共用 modal、flash 機制、CSRF 機制、DELETE JSON 契約）——至今沒有任何 capability 涵蓋，文件與現狀脫節。

本 change 為**文件回填 + 一致性修正**：將共用前端刪除互動契約整理為新 capability `delete-confirmation`，逐資源業務規則 link 回三個 module spec，並汰除舊版 `spec/10`。

## What Changes

- 新增 `delete-confirmation` capability spec，涵蓋現狀行為：
    - `.delete-btn`（`data-url` / `data-name`）觸發共用 `#delete-modal` 確認流程
    - 確認後送 axios DELETE：成功 → 移除對應 `<tr>` + flash server `message`；失敗 → flash `res.data.message`，列保留
    - flash 機制（`utils/flash.js` 的 `autoDismissFlashes` / `showFlash` + `.flash-area`，5 秒淡出）
    - CSRF 採 axios 預設 `XSRF-TOKEN` cookie → `X-XSRF-TOKEN`（Laravel 自動下發 cookie），`bootstrap.js` 設 `X-Requested-With`
    - destroy 端點共用 JSON 契約：`{message}`；not-found → 404、業務拒絕 → 422；逐資源業務規則 link 至各 module spec
    - 刻意設計：MUST NOT 用 inline form POST + 原生 confirm()（被取代）；MUST NOT 抽共用 modal **元件**（各頁自維護 modal HTML，JS 邏輯則共用 `utils/deleteModal.js`）
- 刪除 `spec/10-fix-form-post-axios.md`（由本 spec 取代）

## 程式變更（本 change 一併納入）

舊 spec 回填過程中的設計 review 發現並修正以下項目（commit `65068af`、`98b84e5`）：

- **D：統一 not-found 為 404** — Role / Addon `destroy()` 找不到資源原回 422（與業務拒絕同碼），語義應為 404；對齊 User（`4708b1a` 已改 404）。業務拒絕維持 422。（`65068af`）
- **A：抽共用 `resources/js/utils/deleteModal.js`** — roles/users/addons `index.js` 重複的 `initDeleteModal` 抽為共用 util（與 `utils/flash.js` 同模式）。（`98b84e5`）
- **B：成功改用 server message** — 成功 flash 改用 controller 回傳的 message（「角色已刪除」等），與失敗路徑一致。（`98b84e5`）
- **C：modal 補 Esc / 點背景關閉**。（`98b84e5`）

> 此前 role-management spec 記載「2026-06-12 設計 review 裁示維持現狀、不改 404」，本 change 經使用者裁示**改採 404 一致化**，覆寫該決定。

## Capabilities

### New Capabilities
- `delete-confirmation`: roles / users / addons 列表共用的前端刪除互動契約（axios DELETE + 確認 modal + flash + DOM 移除 + CSRF + DELETE JSON 契約）。內部後台、非公網暴露的威脅模型。

### Modified Capabilities
- `role-management`：刪除角色 not-found 422 → 404（scenario 斷言更新）
- `addon-management`：刪除 not-found 422 → 404（scenario 斷言更新）

## Impact

- **程式碼**：`app/Http/Controllers/RoleController.php`、`app/Http/Controllers/AddonController.php`（各 1 行 422→404）；新增 `resources/js/utils/deleteModal.js`；`resources/js/{roles,users,addons}/index.js` 改 import。現狀實作另涉 `resources/js/utils/flash.js`、`resources/js/bootstrap.js`、`resources/views/admin/{roles,users,addons}/index.blade.php`。
- **文件**：新增 `openspec/specs/delete-confirmation/spec.md`（archive 後）；更新 `role-management`、`addon-management` spec 的 not-found scenario；刪除 `spec/10-fix-form-post-axios.md`；新增 `docs/delete-confirmation-spec.html`。
- **測試**：`tests/Feature/PermissionTest.php`（role not-found 改 404）、`tests/Feature/AddonCrudTest.php`（新增 addon not-found 404 測試）。Scenario 與既有測試一一對應。
