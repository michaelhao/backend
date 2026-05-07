# 使用者管理安全審查與補強

## P1-1. 阻擋管理者修改自己的 role_id

`UserController::update` 加入檢查：若操作對象為目前登入的使用者，且 `role_id` 有變動，reject 並回 `'無法修改自己的角色'`。

name / email / password 不受影響，仍可修改。

---

## P1-2. 密碼政策強化

`AppServiceProvider::boot` 設定 `Password::defaults()`，套用 min:12 + mixedCase + numbers + symbols + uncompromised（HIBP）。

`UserRequest` 與 `ResetPasswordRequest` 的 password rule 改用 `Password::defaults()`，移除原本的 `min:8`。

---

## P1-3. Login Throttle

新增 `app/Http/Middleware/ThrottleLogin.php`，以 `email|IP` 為 key，5 次/60 秒 decay，超限時 redirect back 並顯示中文錯誤訊息（含剩餘秒數）。

`routes/web.php` 的 login POST route 套用此 middleware，移除原本的 `RateLimiter::for('login', ...)` named limiter 方案。

---

## P2-2. Forgot Password Email Enumeration 修復

`ForgotPasswordController::sendResetLinkEmail` 不再根據 `sendResetLink()` 回傳值區分成功/失敗，一律回 `passwords.sent` 中性訊息。

---

## P2-5. 改密碼後踢出其他裝置 Session

`bootstrap/app.php` 在 web middleware group append `AuthenticateSession`。

改密碼後其他裝置的 session 因 password hash stamp 不一致，於下次請求時自動失效。

---

## P2-6. 移除 Post Scaffold

刪除 `PostController`、`PostService`、`PostRepository`、`Post` Model，移除 `/test-db` 路由，新增 `drop_posts_table` migration。

`PermissionSeeder` 移除 `Post` module，`syncPermissions()` 的 `whereNotIn` 邏輯自動清除 DB 中的 orphan permission rows。
