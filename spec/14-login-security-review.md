# 登入系統安全審查與補強

## 背景說明

針對既有登入流程做一次完整的安全性審查（自製 Auth、Session DB driver、bcrypt 12 rounds、權限 attribute-based）。整體基本面合格，但有幾項缺口需補上：

- 密碼重設流程未實作（`password_reset_tokens` 表已存在但無 controller / route）
- 生產環境的 session cookie 未強制走 HTTPS
- 密碼欄位無顯示切換（使用者打字時無法確認是否輸入正確）

審查中已排除「登入暴力破解 throttle」一項——本系統為內部後台、非公網暴露，brute-force 風險不在 scope。

---

## 需新增的檔案

### Controllers

| 檔案 | 用途 |
|---|---|
| `app/Http/Controllers/Auth/ForgotPasswordController.php` | 顯示請求頁 + 寄送重設連結 |
| `app/Http/Controllers/Auth/ResetPasswordController.php` | 顯示重設表單 + 處理新密碼 |

### FormRequests

| 檔案 | 用途 |
|---|---|
| `app/Http/Requests/Auth/ForgotPasswordRequest.php` | 驗證 email |
| `app/Http/Requests/Auth/ResetPasswordRequest.php` | 驗證 token、email、password (`min:8`、`confirmed`) |

### Views

| 檔案 | 用途 |
|---|---|
| `resources/views/auth/forgot-password.blade.php` | 輸入 email 請求重設連結 |
| `resources/views/auth/reset-password.blade.php` | 輸入新密碼 + 確認密碼 |
| `resources/views/components/password-input.blade.php` | 帶眼睛切換的密碼輸入元件（anonymous component） |

### 測試

| 檔案 | 用途 |
|---|---|
| `tests/Feature/Auth/PasswordResetTest.php` | 涵蓋成功、失敗 token、必填驗證、長度驗證等情境 |

---

## 需修改的檔案

| 檔案 | 修改內容 |
|---|---|
| `routes/web.php` | 在 `guest` group 內新增 4 條 `password.*` route |
| `resources/views/auth/login.blade.php` | 加「忘記密碼？」入口連結；密碼欄改用 `<x-password-input>` |
| `resources/views/admin/users/_form.blade.php` | 兩個密碼欄改用 `<x-password-input>` |
| `.env.example` | 新增 `SESSION_SECURE_COOKIE=null` 變數，註解指引 production 必須設為 `true` |

---

## 實作細節

### 1. 密碼重設流程（P0-2）

**前提**：`password_reset_tokens` 表已存在於 `0001_01_01_000000_create_users_table.php`，`config/auth.php` 已設 `expire=60`、`throttle=60`。基礎齊備。

**範圍**：實作 Laravel 標準 4-step flow（請求重設 → 寄信 → 點 link → 設定新密碼），直接用 `Password::` Facade（PasswordBroker），不另抽 Service。

#### `ForgotPasswordController`

```php
public function showLinkRequestForm(): View
{
    return view('auth.forgot-password');
}

public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse
{
    $status = Password::sendResetLink($request->only('email'));

    return $status === Password::ResetLinkSent
        ? back()->with('status', __($status))
        : back()->withErrors(['email' => __($status)]);
}
```

#### `ResetPasswordController`

```php
public function showResetForm(string $token): View
{
    return view('auth.reset-password', [
        'token' => $token,
        'email' => request()->query('email', ''),
    ]);
}

public function reset(ResetPasswordRequest $request): RedirectResponse
{
    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function (User $user, string $password): void {
            $user->forceFill(['password' => $password]);
            $user->setRememberToken(Str::random(60));
            $user->save();
        },
    );

    return $status === Password::PasswordReset
        ? redirect()->route('login')->with('status', __($status))
        : back()->withErrors(['email' => __($status)]);
}
```

> ⚠️ `setRememberToken()` 回傳 `void`，不可鏈式 `->save()`，需拆三行。

#### Routes（`routes/web.php` 的 `guest` group 內）

```php
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
```

---

### 2. Cookie Secure 環境變數（P0-3）

**範圍最小化**：只動 `.env.example`，不改 `AppServiceProvider`、不做 trust proxies、不動 `SESSION_ENCRYPT`。

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
# Production must set SESSION_SECURE_COOKIE=true (HTTPS only)
SESSION_SECURE_COOKIE=null
```

`null` 讓 local dev 維持可走 HTTP；production 部署時手動改 `true`。`config/session.php` 的 `'secure' => env('SESSION_SECURE_COOKIE')` 已是正確寫法，不需改 config。

---

### 3. 密碼顯示切換元件（UI）

**目標**：所有密碼欄位（共 5 個跨 3 個 view）旁邊有眼睛 icon。預設遮罩 + 眼睛關閉，點擊後變明碼 + 眼睛打開。

**做法**：抽 anonymous Blade component，純 vanilla JS + Tailwind，不引入 Alpine/Vue。

#### `resources/views/components/password-input.blade.php`

```blade
@props([
    'name' => 'password',
    'id' => null,
    'placeholder' => '',
    'required' => false,
    'autofocus' => false,
])

<div class="relative">
    <input
        type="password"
        name="{{ $name }}"
        id="{{ $id ?? $name }}"
        @if ($required) required @endif
        @if ($autofocus) autofocus @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        {{ $attributes->merge(['class' => 'w-full border border-gray-300 rounded-md px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500']) }}>

    <button type="button" tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700"
            onclick="
                const i = this.previousElementSibling;
                const isPwd = i.type === 'password';
                i.type = isPwd ? 'text' : 'password';
                this.children[0].classList.toggle('hidden', isPwd);
                this.children[1].classList.toggle('hidden', !isPwd);
            ">
        {{-- eye-slash: 預設顯示（密碼遮罩中） --}}
        <svg ... class="w-5 h-5">...</svg>
        {{-- eye: 點擊後顯示（明碼中） --}}
        <svg ... class="w-5 h-5 hidden">...</svg>
    </button>
</div>
@error($name)
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
```

**設計重點**
- `tabindex="-1"`：眼睛 button 不打斷表單 tab 順序
- `type="button"`：避免成為 form 的 submit
- `pr-10`：input 預留眼睛空間
- `@error` 內建在 component 內，使用方更乾淨
- Icon 用 Heroicons 的 `eye-slash` 與 `eye`（MIT，內嵌 SVG）

#### 使用方式

```blade
<x-password-input name="password" :required="true" autofocus />
<x-password-input name="password_confirmation" :required="true" />
<x-password-input name="password" placeholder="留空則不修改" />
```

---

## 不做的事

- 不引入 Alpine.js / Vue 或任何前端套件
- 不改 `app.js`（toggle JS 內嵌在 component）
- 不抽 PasswordResetService（兩個 controller 都只是薄薄包裝 `Password::` Facade，無業務邏輯）
- 不做 email 驗證（`MustVerifyEmail`）— 屬於 P1，scope 外
- 不做 2FA / 密碼複雜度規則升級 — 屬於 P1/P2，scope 外
- 不做登入 throttle — 內部後台，brute-force 不在 threat model

---

## 驗證測試

### 自動化

```bash
docker compose exec -u www-data backend-api php artisan test --compact --filter=PasswordResetTest
docker compose exec -u www-data backend-api php artisan test --compact   # 全測
```

`PasswordResetTest` 涵蓋：
- forgot password 頁面可渲染
- 提交 email 後 `ResetPassword` notification 已寄出（用 `Notification::fake()`）
- reset password 頁面可用 token 渲染
- 用有效 token 可成功重設密碼，新密碼可登入
- 無效 token 不會修改密碼
- email 必填驗證
- 密碼最少 8 字元驗證

### 手動

1. `/login` 點「忘記密碼？」→ 進到 `/forgot-password`
2. 填 email 送出 → 看 `storage/logs/laravel-*.log`（dev `MAIL_MAILER=log`）取得 reset link
3. 點 link → 進到 `/reset-password/{token}` 重設頁
4. 設定新密碼 → 導回 `/login`，可用新密碼登入
5. 三個 view 的密碼欄都可點眼睛切換 `*****` ↔ `12345`
6. tab 鍵不會跳到眼睛 button（不打斷填表）
7. 切換後表單 submit 仍正常（`type` 不影響 form data）

### Production 部署

- `.env` 設 `SESSION_SECURE_COOKIE=true`
- DevTools → Application → Cookies 檢查 session cookie 已帶 `Secure` flag
- `MAIL_MAILER` 改為實際 mailer（log → smtp/ses/mailgun 等）
- `MAIL_FROM_ADDRESS` 改為正式 from address（不再是 `hello@example.com`）

---

## 後續可做（不在本次 scope）

| 等級 | 項目 |
|---|---|
| P1 | 開啟 `SESSION_ENCRYPT=true`（需一次性 logout 全部使用者） |
| P1 | 實作 `MustVerifyEmail`（管理員建帳號暫不需要，未來自助註冊則必須） |
| P1 | 2FA / TOTP（給 Admin role） |
| P2 | 密碼強度規則升級（大小寫、特殊字元、`uncompromised()`） |
| P2 | `users.role_id` 加 foreign key 約束 |
| P2 | User vs ShopAdmin 加密策略一致化（ShopAdmin email 用 `encrypted` cast） |
