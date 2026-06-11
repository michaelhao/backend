# auth Specification (delta)

## ADDED Requirements

### Requirement: 登入頁存取

系統 SHALL 在 `GET /login` 提供登入頁（guest 限定）。已登入使用者存取 `/login` SHALL 被 302 導向 `/`。

#### Scenario: 訪客可見登入頁
- **WHEN** 訪客 GET `/login`
- **THEN** 系統回應 200 並渲染登入表單（email、密碼、忘記密碼連結）

#### Scenario: 已登入者被導離登入頁
- **GIVEN** 已登入使用者
- **WHEN** GET `/login`
- **THEN** 系統回應 302 導向 `/`

---

### Requirement: 未登入導向

未登入使用者存取任何受 `auth` middleware 保護的路由 SHALL 被 302 導向 `/login`，登入成功後 SHALL 回到原請求路徑（`redirect()->intended()`）。

#### Scenario: 訪客存取受保護路由
- **WHEN** 訪客 GET `/`
- **THEN** 系統回應 302 導向 `/login`

---

### Requirement: 登入驗證

`POST /login` SHALL 以 `LoginRequest` 驗證輸入：`email` 必填且須為 email 格式、`password` 必填。驗證失敗 SHALL 回到登入頁並顯示對應欄位錯誤，且維持未登入。

#### Scenario: 缺少必填欄位
- **WHEN** POST `/login` 不帶 email 與 password
- **THEN** session errors 包含 `email` 與 `password`
- **AND** 使用者維持未登入

---

### Requirement: 登入成功

憑證正確時系統 SHALL 依序：(1) regenerate session id（防 session fixation）、(2) 將使用者角色權限載入 session（`auth.permissions`）、(3) 302 導向 intended 路徑（預設 `/`）。

#### Scenario: 正確帳密登入
- **GIVEN** 已存在的使用者
- **WHEN** POST `/login` 帶正確 email 與密碼
- **THEN** 系統回應 302 導向 `/`
- **AND** 使用者已通過認證
- **AND** session 含 `auth.permissions`

---

### Requirement: 登入失敗

憑證錯誤時系統 SHALL 回到登入頁、顯示**不區分帳號或密碼錯誤**的通用訊息「帳號或密碼錯誤」（掛在 `email` 欄位），且只保留 email 輸入值（不保留密碼）。

#### Scenario: 密碼錯誤
- **GIVEN** 已存在的使用者
- **WHEN** POST `/login` 帶正確 email 與錯誤密碼
- **THEN** session errors 包含 `email`
- **AND** 使用者維持未登入

---

### Requirement: 登入節流

`POST /login` SHALL 以 `ThrottleLogin` middleware 限制同一 key 在 60 秒內最多 5 次嘗試。key SHALL 為 `transliterate(lower(email)) + '|' + 來源 IP`（email 大小寫變形不得繞過計數）。超限時 SHALL 回登入頁並顯示含剩餘等候秒數的錯誤訊息。登入成功 SHALL 立即清除該 key 的計數。

節流定位為防呆而非防暴力破解（內部後台、非公網暴露，brute-force 不在威脅模型內）。

#### Scenario: 超過嘗試上限被擋下
- **GIVEN** 同一 email 與 IP 已失敗嘗試 5 次
- **WHEN** 第 6 次 POST `/login`（即使憑證正確）
- **THEN** session errors 包含 `email`（節流訊息）
- **AND** 使用者維持未登入

#### Scenario: 大小寫變形不繞過節流
- **GIVEN** 以 `ADMIN@example.com` 失敗嘗試 5 次
- **WHEN** 以 `admin@example.com` POST `/login`
- **THEN** 該次嘗試被節流擋下

#### Scenario: 成功登入清除計數
- **GIVEN** 失敗嘗試 4 次後成功登入並登出
- **WHEN** 再次以正確憑證 POST `/login`
- **THEN** 系統回應 302 導向 `/`（不被節流）
- **AND** 使用者已通過認證

---

### Requirement: 登出

`POST /logout`（auth 限定）SHALL 依序：登出使用者、invalidate session、regenerate CSRF token，並 302 導向 `/login`。

#### Scenario: 登出
- **GIVEN** 已登入使用者
- **WHEN** POST `/logout`
- **THEN** 系統回應 302 導向 `/login`
- **AND** 使用者維持未登入

---

### Requirement: 無 remember me（刻意設計）

系統 MUST NOT 提供「記住我」長效登入。`users.remember_token` 欄位已由 migration `2026_05_04_113610_drop_remember_token_from_users_table` 刻意移除；登入表單 MUST NOT 出現記住我選項。

#### Scenario: 登入表單無記住我
- **WHEN** 訪客 GET `/login`
- **THEN** 表單僅含 email 與密碼欄位，無 remember 勾選框

---

### Requirement: 忘記密碼（防帳號列舉）

`POST /forgot-password`（guest 限定）SHALL 驗證 email 必填與格式，並寄送重設連結。**不論該 email 是否存在或被 broker 節流**，系統 SHALL 一律回應相同的固定訊息「若該 email 存在，我們已寄出重設連結」，不得洩漏帳號存在性。

#### Scenario: 請求重設連結
- **GIVEN** 已存在的使用者
- **WHEN** POST `/forgot-password` 帶該使用者 email
- **THEN** session 含 `status` 訊息
- **AND** 系統對該使用者寄出 `ResetPassword` 通知

#### Scenario: email 必填
- **WHEN** POST `/forgot-password` 不帶 email
- **THEN** session errors 包含 `email`

---

### Requirement: 重設密碼表單

`GET /reset-password/{token}`（guest 限定）SHALL 渲染重設表單，token 由路徑帶入、email 由 query string 帶入。

#### Scenario: 開啟重設表單
- **GIVEN** 透過重設通知取得的有效 token
- **WHEN** GET `/reset-password/{token}?email=...`
- **THEN** 系統回應 200 並渲染重設密碼表單

---

### Requirement: 密碼強度規則

重設密碼的新密碼 SHALL 符合：最少 12 字元、含大小寫字母、數字與符號，且須通過確認欄位（`confirmed`）。系統 MUST NOT 使用 `Password::uncompromised()`（HIBP 外部 API）——正式環境無對外網際網路連線，外部呼叫必定失敗。

#### Scenario: 密碼過短被拒
- **WHEN** POST `/reset-password` 帶長度不足 12 的密碼
- **THEN** session errors 包含 `password`

---

### Requirement: 重設密碼成功

token 與 email 正確時系統 SHALL 依序：(1) 更新密碼（經 `hashed` cast 雜湊）、(2) 刪除該使用者**所有** database sessions（既有登入立即失效）、(3) 發送 `Illuminate\Auth\Events\PasswordReset` 事件，並 302 導向 `/login` 帶成功訊息。

#### Scenario: 以有效 token 重設
- **GIVEN** 透過重設通知取得的有效 token
- **WHEN** POST `/reset-password` 帶 token、email 與合規新密碼
- **THEN** 系統回應 302 導向 `/login` 並帶 `status` 訊息
- **AND** 新密碼可通過 Hash 驗證

#### Scenario: 重設後既有 sessions 失效且發送事件
- **GIVEN** 該使用者於 `sessions` 表存在既有 session 記錄
- **WHEN** 以有效 token 完成重設
- **THEN** `sessions` 表不再有該使用者的記錄
- **AND** `PasswordReset` 事件已發送

---

### Requirement: 重設密碼失敗

token 無效或與 email 不符時，系統 SHALL 顯示錯誤訊息「重設連結已過期或無效，請重新申請」且 MUST NOT 變更密碼。

#### Scenario: 無效 token
- **GIVEN** 已存在的使用者
- **WHEN** POST `/reset-password` 帶無效 token
- **THEN** session errors 包含 `email`
- **AND** 使用者密碼維持原雜湊值
