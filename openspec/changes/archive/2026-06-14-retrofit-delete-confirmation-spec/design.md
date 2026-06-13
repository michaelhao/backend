# design — retrofit-delete-confirmation-spec

本 change 同時是文件回填與小幅一致性修正，記錄關鍵設計決策。

## 為何另立 capability，而非併入三個 module spec

`spec/10` 的主題是**互動機制**（前端如何送 DELETE、如何確認、如何顯示結果、CSRF 怎麼帶），橫跨 roles / users / addons 三頁且行為完全一致。若併入各 module spec 會產生三份重複描述；若只放在某一個 module spec 又不該由它「擁有」。因此抽為獨立 capability `delete-confirmation`，逐資源的業務規則（哪些角色不可刪、刪 user 連帶清 sessions 等）仍留在各自 module spec，本 spec 以 link 指向。

## not-found 一律 404（覆寫先前裁示）

歷史上 User destroy 在 `4708b1a` 已把 not-found 從 422 改為 404（理由：422 語意為驗證錯誤，not-found 應為 404），但 Role 當時裁示「維持現狀、不改 404」，Addon 未revisit。結果三者分歧（User=404 / Role=422 / Addon=422）。

本次設計 review 經使用者裁示**一致化為 404**：找不到資源 → 404；業務拒絕（仍有使用者 / 刪自己 / 帳單參照 / 已刪除等）→ 422。前端對任何非 2xx 一律取 `res.data.message` 顯示 flash，故此變更不影響 UX，純為語意正確與跨模組一致。

## CSRF：依賴 axios 預設 XSRF cookie，非 meta token

舊 spec 範例示意以 `meta[name=csrf-token]` 手動設 `X-CSRF-TOKEN`，但實作未採此法。`bootstrap.js` 僅設 `X-Requested-With: XMLHttpRequest`；CSRF 由 axios 內建的 `XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header 機制處理（Laravel 每個 response 自動下發 `XSRF-TOKEN` cookie，`VerifyCsrfToken` 接受該 header）。此為刻意採用框架預設，spec 以此實作為準。

## 共用 JS 邏輯但不共用 modal 元件

舊 spec 明訂「各頁自維護 modal HTML，不抽共用元件」——保留各頁 blade 內的 `#delete-modal` 標記。但三頁的 `initDeleteModal` JavaScript 完全重複，故本次把**邏輯**抽到 `resources/js/utils/deleteModal.js`（與既有 `utils/flash.js` 同模式）。即：HTML 各自維護、行為共用，兩者不衝突。

## 軟刪除 vs 硬刪除（沿用 module spec）

Addon 為軟刪除（`status = -1`），Role / User 為硬刪除。前端一律「移除 DOM 列」，因列表本就排除已刪除項目。此差異屬各 module spec 範疇，本 spec 僅在 JSON 契約處註記。
