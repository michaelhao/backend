# add-auth-spec

## Why

登入功能歷經三個階段演進——舊版實作方案 [spec/1-login.md](../../spec/1-login.md)、安全審查補強 [spec/14-login-security-review.md](../../spec/14-login-security-review.md)（密碼重設流程、password-input 元件），以及 2026-06-11 的設計 review 修正（commit `4adf510` 分層重構 / throttle 修正 / 重設後 sessions 失效、commit `9339bf4` 移除 HIBP 外部檢查）——但 `openspec/specs/` 至今沒有 auth capability，文件與現狀脫節，且舊版 spec 檔的內容（如「記住我」）已與實作不符。

本 change 為**文件回填**：將三個階段的內容合併為一份反映現狀的 `auth` capability spec，並汰除舊版 spec 檔。

## What Changes

- 新增 `auth` capability spec，涵蓋現狀行為：
    - 登入/登出（guest/auth 導向、session regenerate/invalidate、權限載入 session）
    - 登入節流（5 次 / 60 秒、email 正規化 key、成功登入清除計數）
    - 密碼重設（防帳號列舉、重設後其他 sessions 失效 + `PasswordReset` 事件）
    - 密碼強度規則（min 12 + 大小寫 + 數字 + 符號；**無** HIBP `uncompromised()`——正式環境無對外連線）
    - 刻意不做的設計：無 remember me（`remember_token` 欄位已移除）、無帳號鎖定 / 2FA（內部後台威脅模型）
- 刪除 `spec/1-login.md` 與 `spec/14-login-security-review.md`（由本 spec 取代）
- **無程式變更**：對應實作已在 commit `4adf510`、`9339bf4` 完成並通過全套件測試（211 passed）

## Capabilities

### New Capabilities
- `auth`: 後台登入/登出、登入節流與密碼重設的完整行為規格（內部後台、非公網暴露的威脅模型）

### Modified Capabilities
<!-- 無。 -->

## Impact

- **程式碼**：無變更（純文件）。spec 描述的現狀實作位於 `app/Http/Controllers/Auth/`、`app/Services/AuthService.php`、`app/Http/Middleware/ThrottleLogin.php`、`app/Http/Requests/Auth/`、`routes/web.php`
- **文件**：新增 `openspec/specs/auth/spec.md`（archive 後）；刪除 `spec/1-login.md`、`spec/14-login-security-review.md`
- **測試**：無變更。spec 的 Scenario 與既有測試一一對應（`tests/Feature/Auth/LoginTest.php` 9 案例、`tests/Feature/Auth/PasswordResetTest.php` 8 案例）
