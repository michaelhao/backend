# delete-confirmation Specification

## Purpose
TBD - created by archiving change retrofit-delete-confirmation-spec. Update Purpose after archive.
## Requirements
### Requirement: 刪除按鈕觸發確認 modal

列表頁的刪除入口 SHALL 為 `type="button"` 的 `.delete-btn`，帶 `data-url`（DELETE 目標 URL）與 `data-name`（顯示名稱），MUST NOT 為 inline `<form>`。點擊 `.delete-btn` SHALL 開啟該頁共用的 `#delete-modal`，並將 `data-name` 填入 `#delete-modal-name`。Modal SHALL 可由「取消」鈕、`Escape` 鍵、或點擊 overlay 背景關閉，關閉時清除待刪目標。

#### Scenario: 點擊刪除開啟確認 modal
- **GIVEN** 列表含一筆資料、其刪除鈕帶 `data-url` 與 `data-name`
- **WHEN** 使用者點擊該 `.delete-btn`
- **THEN** `#delete-modal` 顯示，且 `#delete-modal-name` 為該筆 `data-name`

#### Scenario: 取消 / Esc / 點背景關閉 modal
- **GIVEN** `#delete-modal` 開啟中
- **WHEN** 使用者點「取消」、按 `Escape`、或點 overlay 背景
- **THEN** modal 關閉且不送出任何請求

---

### Requirement: 確認後送 axios DELETE

確認鈕按下時，前端 SHALL 以 `window.axios.delete(data-url)` 送出請求，過程中 SHALL 禁用確認鈕並顯示「刪除中...」。回應 2xx 時 SHALL 自 DOM 移除該列對應的 `<tr>`，並以 server 回傳的 `message` 顯示成功 flash。回應非 2xx 時 SHALL **保留該列**，並以 `res.data.message`（無則通用訊息）顯示錯誤 flash。無論成功或失敗，結束時 SHALL 關閉 modal、還原確認鈕狀態。

#### Scenario: 刪除成功移除列
- **GIVEN** 確認 modal 指向某列的 `data-url`
- **WHEN** axios DELETE 回應 200
- **THEN** 該列 `<tr>` 自 DOM 移除
- **AND** 顯示成功 flash，內容為 server 回傳的 `message`

#### Scenario: 刪除失敗保留列並顯示訊息
- **GIVEN** 確認 modal 指向某列的 `data-url`
- **WHEN** axios DELETE 回應非 2xx（含 422 / 404）
- **THEN** 該列保留於畫面
- **AND** 顯示錯誤 flash，內容為 `res.data.message`

---

### Requirement: flash 訊息機制

前端動態 flash SHALL 由 `resources/js/utils/flash.js` 提供：`showFlash(type, message)` SHALL 將訊息插入 `.flash-area` 並於 5 秒後淡出移除；`autoDismissFlashes()` SHALL 對既有 server 端 `.flash-message`（`session('success')` / `session('error')` 渲染者）套用相同的 5 秒淡出。頁面 SHALL 提供 `.flash-area` 容器供插入。

#### Scenario: 動態 flash 自動淡出
- **WHEN** `showFlash('success', '角色已刪除')` 被呼叫
- **THEN** `.flash-area` 內出現該訊息
- **AND** 約 5 秒後自動淡出移除

---

### Requirement: CSRF 採 axios 預設 XSRF cookie

刪除請求的 CSRF 保護 SHALL 依賴 axios 內建的 `XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header 機制（Laravel 每個 response 自動下發 `XSRF-TOKEN` cookie，`VerifyCsrfToken` 接受該 header）。`resources/js/bootstrap.js` SHALL 設定 `X-Requested-With: XMLHttpRequest`。系統 MUST NOT 依賴頁面手動以 `meta[name=csrf-token]` 設定 `X-CSRF-TOKEN` 作為刪除請求的 CSRF 來源。

#### Scenario: 帶 CSRF 的 DELETE 通過驗證
- **GIVEN** 已登入的 session（持有 `XSRF-TOKEN` cookie）
- **WHEN** 前端以 axios 送出 DELETE
- **THEN** 請求通過 CSRF 驗證並抵達 controller（非 419）

---

### Requirement: destroy 端點共用 JSON 契約

採本互動的 destroy 端點（`DELETE /roles/{id}`、`DELETE /users/{id}`、`DELETE /addons/{id}`）SHALL 一律回傳 JSON `{ "message": ... }`，MUST NOT 回 redirect。狀態碼語義 SHALL 一致：成功回 **200**；**找不到資源回 404**；**業務規則拒絕回 422**。逐資源的成功訊息、業務拒絕條件與連帶副作用（pivot / sessions 清理、軟刪除等）SHALL 依各自 capability：[[role-management]]、[[user-management]]、[[addon-management]]。

#### Scenario: 成功回 200 JSON
- **GIVEN** 持有對應 delete 權限的使用者
- **WHEN** DELETE 一筆可刪除的資源
- **THEN** 系統回應 200 與 JSON `{message}`（資源已自畫面移除）

#### Scenario: 找不到資源回 404
- **WHEN** DELETE 一個不存在的 id（roles / users / addons 任一）
- **THEN** 系統回應 404 與 JSON `{message}`

#### Scenario: 業務規則拒絕回 422
- **GIVEN** 一筆因業務規則不可刪除的資源（如仍有使用者的角色、帳單業務、刪除自己）
- **WHEN** DELETE 該資源
- **THEN** 系統回應 422 與 JSON `{message}`
- **AND** 該資源仍存在

---

### Requirement: 不採用 inline form POST + 原生 confirm（刻意設計）

刪除互動 MUST NOT 使用「表格內 inline `<form method=POST>` + `@method('DELETE')` + `onsubmit=confirm()`」的整頁 reload 舊法。此舊法已於 commit `b824d18` 自三頁移除，改為本 capability 描述的 axios + 自訂 modal 流程。

#### Scenario: 列表不含 inline 刪除表單
- **WHEN** 渲染 roles / users / addons 列表
- **THEN** 列內刪除入口為 `.delete-btn` 按鈕，畫面不含 per-row 的刪除 `<form>`

---

### Requirement: 不抽共用 modal 元件（刻意設計）

各列表頁 SHALL 自行於 blade 維護其 `#delete-modal` 標記，系統 MUST NOT 將 modal HTML 抽為共用 blade component。互動**邏輯**則 SHALL 共用 `resources/js/utils/deleteModal.js` 的 `initDeleteModal()`，避免三頁 JavaScript 重複。

#### Scenario: modal HTML 各頁自維護、JS 邏輯共用
- **WHEN** 檢視三頁的刪除實作
- **THEN** 各頁 blade 各有自己的 `#delete-modal` 標記
- **AND** 三頁 `index.js` 皆 import 同一個 `utils/deleteModal.js` 的 `initDeleteModal`

