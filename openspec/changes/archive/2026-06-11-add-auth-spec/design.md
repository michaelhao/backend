# design — add-auth-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit（`4adf510`、`9339bf4`），本 change 只產出 spec 文件、不寫程式。設計重點在於「spec 內容如何忠實反映現狀的設計決策」，以下記錄已實作架構背後的關鍵決策，作為 spec Requirements 的依據。

## Goals / Non-Goals

**Goals:**
- 一份 `auth` capability spec 完整描述登入/登出/節流/密碼重設的可觀察行為
- 每個 Scenario 對應一個既有測試案例，spec 可被測試驗證
- 記錄「刻意不做」的設計與其威脅模型依據，避免後人誤判為缺漏

**Non-Goals:**
- 不改任何程式碼、不新增測試
- 不規範使用者管理（CRUD）的密碼規則細節（屬 user-management 範疇，僅在此記錄共用的強度規則）
- 不涵蓋權限系統本身（`CheckPermission` / permission 解析屬另一 capability）

## Decisions

1. **Service 層不碰 HTTP**：`AuthService` 只操作 `Auth` facade 與權限載入；`session()->regenerate()/invalidate()/regenerateToken()` 由 `LoginController` 執行。對齊專案分層規範（Service 對 HTTP 零知識）。
2. **節流 key 正規化**：`Str::transliterate(Str::lower(email)) + '|' + ip`，避免大小寫變形繞過計數；成功登入即 `RateLimiter::clear()`，正常重複登入不累積。節流定位為防呆而非防暴力破解（內部後台、非公網暴露）。
3. **無 remember me**：`remember_token` 欄位已由 migration `2026_05_04_113610` 刻意移除。內部後台不提供長效登入。
4. **無 HIBP `uncompromised()`**：正式環境無對外網際網路連線，外部 API 呼叫必定失敗。密碼強度由 min 12 + 大小寫 + 數字 + 符號保證。
5. **防帳號列舉**：忘記密碼一律回固定訊息「若該 email 存在，我們已寄出重設連結」，不論 email 是否存在或被 broker 節流。
6. **重設後既有登入失效**：直接刪除該 user 的 database sessions（`SESSION_DRIVER=database`），並發 `PasswordReset` 事件。`AuthenticateSession` middleware 的密碼雜湊比對為第二層保險。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → 每個 Scenario 綁定一個既有測試；行為變更時測試會先失敗，提醒同步更新 spec
- [刪除 sessions 走 raw DB 查詢（`sessions` 表無 model）] → 集中在 `UserRepository::deleteSessionsByUserId()`，符合 Repository 封裝資料存取的分層
- [節流 key 含 IP，共用出口 IP 的內網使用者會互相影響計數] → 接受：內部後台使用者數少，且 60 秒即衰減
