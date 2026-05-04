# 角色管理安全審查與補強

## 背景說明

針對既有角色管理流程做一次完整的安全性審查（自製權限系統、Role/Permission/User 三表、attribute-based middleware、session 快取權限）。整體架構分層清楚、Form Request / Repository / Service 都到位，但有幾項缺口需補上：

- session 中的權限只在登入時載入一次，管理者異動角色權限後**已登入使用者不會即時生效**，需登出/過期才會更新
- Remember-me 自動登入機制本身會繞過 `AuthService::loginSession()`、且長 lifetime cookie 一旦被竊取會放大暴露窗口；本次決定**整個移除 remember-me**，靠 session lifetime 強制 2 小時不活動即重登
- `default_route` 只驗證 `exists:permissions,name`，但 permission 存在 ≠ 命名路由存在；refactor 後若 route 被移除，使用者會卡住進不去任何頁
- `session('permissions')` key 命名太通用，易與其他模組撞名
- `RoleRepository::getByName()` 內部用 `firstOrFail()` 但名字不體現會丟例外

審查中已排除以下項目，不在本次 scope：

- **P1 自我提權路徑**：屬刻意設計（持有 `Role.update` / `User.update` 的人允許自行調整角色與權限）
- **P2 操作稽核 log**：暫不需要，內部後台不引入 `spatie/laravel-activitylog`
- **登入暴力破解 throttle**：內部後台、非公網暴露，brute-force 不在 threat model

---

## 需新增的檔案

### Services

| 檔案 | 用途 |
|---|---|
| `app/Services/PermissionRouteResolver.php` | 從 middleware 抽出 permission ↔ route 對應邏輯，供 middleware 與 RoleRequest 共用 |

### Migrations

| 檔案 | 用途 |
|---|---|
| `database/migrations/<timestamp>_drop_remember_token_from_users_table.php` | 移除 `users.remember_token` 欄位（不再使用 remember-me） |

---

## 需修改的檔案

| 檔案 | 修改內容 |
|---|---|
| `app/Http/Middleware/CheckPermission.php` | 改用 `PermissionRouteResolver`；加入 session 版本戳檢查，stale 時自動 reload 權限；加 `User` 型別提示；移除原本的 static cache |
| `app/Models/Traits/HasPermissions.php` | session key 改為 `auth.permissions` / `auth.permissions_version`；新增 `currentPermissionsVersion()`、`permissionsSessionIsStale()` |
| `app/Repositories/RoleRepository.php` | `syncPermissions()` 內呼叫 `$role->touch()` 更新版本戳；`getByName` → `findByNameOrFail` |
| `app/Providers/AppServiceProvider.php` | 註冊 `PermissionRouteResolver` singleton |
| `app/Http/Requests/RoleRequest.php` | `default_route` 加自訂 rule 確認對應到實際命名路由 |
| `app/Services/AuthService.php` | `attempt()` 簽名移除 `bool $remember`；不再產生 remember cookie |
| `app/Http/Controllers/Auth/LoginController.php` | 不再讀取 `remember` 欄位 |
| `app/Http/Controllers/Auth/ResetPasswordController.php` | 移除 `setRememberToken` 呼叫（不再有 remember cookie 需失效） |
| `app/Models/User.php` | `#[Hidden]` 移除 `remember_token`（欄位已 drop） |
| `database/factories/UserFactory.php` | 移除 `remember_token` 預設值 |
| `resources/views/auth/login.blade.php` | 移除「記住我」checkbox |
| `tests/Feature/PermissionTest.php` | 新增 3 個測試 |

---

## 實作細節

### 1. Session 權限即時撤銷（P2）+ 移除 Remember-me

**問題**
- `loadPermissionsToSession()` 僅在 `AuthService::loginSession()` 中被呼叫；管理者修改 Role 權限或使用者 `role_id` 後，已登入者 session 內權限不會更新。
- Remember-me 自動登入會繞過 `loginSession()` 與 session 版本戳機制，且 5 年 lifetime 的 cookie 一旦被竊取會放大暴露窗口；本次決定整個拆除，不再依賴 listener 補洞。

**設計重點**
- 用 `max(users.updated_at, roles.updated_at).timestamp` 當作版本戳，**不需新增欄位、不需 migration**（版本戳本身）。
- 異動 role permissions 時於 `RoleRepository::syncPermissions()` 內 `$role->touch()`；異動 `users.role_id` 時 Eloquent 會自動 touch `users.updated_at`。
- middleware 在每個 protected request 入口比對 session 版本戳與 DB 當前版本戳，不一致就 reload。
- `loadPermissionsToSession()` 內先 `$this->unsetRelation('role')` 再讀取 — 避免 user.role_id 已被 update 但 Eloquent 仍快取舊角色關聯，造成載入到錯誤的角色權限。
- 完全移除 remember-me：登入表單沒有「記住我」checkbox、`AuthService::attempt()` 不傳 remember flag、不再發 `remember_web_*` cookie、`users.remember_token` 欄位以 migration drop 掉、`ResetPasswordController` 不再呼叫 `setRememberToken`。session lifetime 為 120 分鐘，2 小時不活動即過期需重登。
- 沒掛 `permission` middleware 但仍會呼叫 `hasPermissionTo()` 的場景（例如直接顯示 sidebar 的 layout），仰賴 `loginSession()` 在登入當下已寫入 session；測試的 `actingAs($user)` 場景需自行呼叫 `loadPermissionsToSession()` 或進入有 `permission` middleware 的路由觸發 stale reload。

**`HasPermissions` trait 重點**

```php
public function loadPermissionsToSession(): void
{
    // 確保讀到最新的 role 資料 — 避免 Eloquent 快取住的舊關聯造成 role_id 已換但載入舊角色權限
    $this->unsetRelation('role');

    $permissions = $this->role
        ? $this->role->permissions()->pluck('name')->toArray()
        : [];

    session([
        'auth.permissions' => $permissions,
        'auth.permissions_version' => $this->currentPermissionsVersion(),
    ]);
}

public function currentPermissionsVersion(): ?int
{
    if (! $this->role_id) {
        return null;
    }

    // 直接走 DB 查詢以避免取到 Eloquent 快取住的舊值（測試 actingAs 場景特別重要）
    $userTs = static::query()->where('id', $this->getKey())->value('updated_at');
    $roleTs = Role::query()->where('id', $this->role_id)->value('updated_at');

    return max(
        $userTs?->getTimestamp() ?? 0,
        $roleTs?->getTimestamp() ?? 0,
    );
}

public function permissionsSessionIsStale(): bool
{
    if (! session()->has('auth.permissions')) {
        return true;
    }

    return session('auth.permissions_version') !== $this->currentPermissionsVersion();
}
```

> ⚠️ 版本戳 **必須** 從 DB 取，不可依賴 `$this->updated_at` / `$this->role->updated_at` — 在測試 `actingAs($user)` 場景下這些屬性會被 Eloquent 快取為舊值，造成版本永遠相等，永遠不 reload。

**`CheckPermission` middleware 重點**

```php
public function __construct(private PermissionRouteResolver $resolver) {}

public function handle(Request $request, Closure $next): Response
{
    /** @var User $user */
    $user = $request->user();
    // ... resolve permission name ...

    if (! $user->role_id) {
        return redirect()->route('no-role');
    }

    if ($user->permissionsSessionIsStale()) {
        $user->loadPermissionsToSession();
    }

    if (! $user->hasPermissionTo($permissionName)) {
        // ... default_route fallback redirect ...
    }

    return $next($request);
}
```

> ℹ️ `/** @var User $user */` 是因為 `$request->user()` 回傳型別為 `?Authenticatable`，靜態分析器看不到 trait 上的 `permissionsSessionIsStale()` / `loadPermissionsToSession()`；這個 PHPDoc 讓 IDE 正確解析方法。`auth` middleware 已保證 user 存在且為 `User`，不需 runtime check。

**`AppServiceProvider` 重點**

```php
public function register(): void
{
    $this->app->singleton(PermissionRouteResolver::class);
}

public function boot(): void
{
    //
}
```

---

### 2. `default_route` 必須對應到實際命名路由（P3）

**問題**
原本只驗 `exists:permissions,name`，但 permission 存在 ≠ route 存在。例如 seeder 中有 `Foo.bar` permission 但 `FooController@bar` 已被刪除 → 設為 `default_route` 後使用者進不去任何頁。

**做法**
抽出 `PermissionRouteResolver`，提供 `permissionFor(controller, method)` 與 `routeNameFor(permission)` 兩個方法；`RoleRequest` 用 closure rule 驗證 `default_route` 能解析到實際命名路由。

**`PermissionRouteResolver` 介面**

```php
public function permissionFor(string $controller, string $method): string;
public function routeNameFor(string $permission): ?string;
public function clearCache(): void;
```

內部 lazily 建構一張 permission → route name map，遍歷所有 named routes 並用同樣的 attribute / fallback 邏輯推導 permission key。Closure routes 沒有 controller 自然被略過。

**`RoleRequest` 自訂 rule**

用 closure rule 並抽成獨立 method，rules() 表保持精簡可讀：

```php
public function rules(): array
{
    return [
        // ...
        'default_route' => [
            'required',
            'string',
            'exists:permissions,name',
            $this->defaultRouteResolvableRule(),
        ],
        // ...
    ];
}

/**
 * 確認 default_route 對應的 permission 能解析到實際命名路由
 * （permission 存在於 DB ≠ controller method 仍存在）。
 */
private function defaultRouteResolvableRule(): Closure
{
    return function (string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (app(PermissionRouteResolver::class)->routeNameFor($value) === null) {
            $fail('所選的預設頁面尚未對應到任何路由。');
        }
    };
}
```

---

### 3. Session key 改為 namespaced（P3）

`session('permissions')` → `session('auth.permissions')`；同時新增 `session('auth.permissions_version')`。
舊 session 不相容，但搭配項目 1 的 reload 機制即可自然恢復（middleware 偵測到 key 不存在 → 重新 load）。**不需資料遷移、不需強制使用者重登。**

---

### 4. `RoleRepository::getByName` → `findByNameOrFail`（P4）

`getByName()` 內部用 `firstOrFail()` 但名字未體現會丟例外，與其他 `find...OrFail` 命名慣例不一致。
全專案 grep 確認無外部呼叫端後重命名（`PermissionRepository::getByName()` 是 nullable 回傳，名字維持原樣）。

---

## 不做的事

- 不修「自我提權」路徑（持有 `Role.update` / `User.update` 即可改自己角色，屬刻意設計）
- 不引入 audit log / `spatie/laravel-activitylog`（暫不需要）
- 不加 `is_system` flag 或保護 Admin 角色（同上，提權屬刻意設計）
- 不改 controllers 用 route model binding（保留現行 redirect-with-flash UX，4b 視為另議）
- 不加 `users.role_id` foreign key 約束（屬另一個 P2，scope 外）
- 不引入 Redis cache 權限（DB 版本戳已足夠，內部後台流量低）

---

## 驗證測試

### 自動化

```bash
docker compose exec backend-api php artisan test --compact --filter=PermissionTest
docker compose exec backend-api php artisan test --compact   # 全測
```

`PermissionTest` 新增 3 個測試：

- `test_role_request_rejects_default_route_without_named_route`
  建立一個存在於 `permissions` 但無對應 route 的 permission，POST `/roles` 設為 `default_route` → `assertSessionHasErrors('default_route')`。
- `test_permission_session_reloads_when_role_updated_at_advances`
  使用者已可訪問 `/roles`；管理者拔掉該角色的 `Role.index` 權限並 advance `roles.updated_at` → 同 session 再次請求應自動 reload，導向 default_route。
- `test_permission_session_reloads_when_user_role_id_changes`
  把使用者 `role_id` 換成只擁有 `Dashboard.index` 的最小角色並 advance `users.updated_at` → 同 session 再次請求應 reload 並導向 default_route，同時驗證 `unsetRelation('role')` 修補有效。

### 手動

1. 兩個瀏覽器：A 用 Admin 登入、B 用 Viewer 登入。
2. A 把 Viewer 角色的 `Dashboard.index` 之外的權限全拿掉。
3. B 重新整理任意管理頁面 → 應立即被導回 dashboard，無需登出。
4. 登入頁應已不存在「記住我」checkbox；登入成功後 DevTools → Application → Cookies 應只有 `<APP_NAME>_session`，不應有 `remember_web_*`。
5. 嘗試在角色編輯頁把 `default_route` 設為一個沒有對應命名路由的 permission（需先手動建一個 orphan permission）→ 表單應報錯。

---

## 影響範圍

- **行為改變**：管理者異動角色權限後，受影響使用者**下一次 request** 即生效（原本要登出 / session 過期）。
- **行為改變**：移除 remember-me — 登入頁不再有「記住我」checkbox、session 過期（120 分鐘不活動）必須重新輸入帳密。
- **行為改變**：`default_route` 設定時會多一層命名路由存在性驗證。
- **Schema 變動**：新增 migration drop `users.remember_token` 欄位。
- **每個 protected request 多 2 個輕量 SELECT**（取 `users.updated_at` 與 `roles.updated_at`）— 內部後台流量可接受。

---

## 後續可做（不在本次 scope）

| 等級 | 項目 |
|---|---|
| P3 | RoleController 改用 route model binding，把 `Role::find($id) + redirect-with-flash` 樣板集中到全域 missing handler |
| P2 | `users.role_id` 加 foreign key 約束 |
| P2 | 限制非 Admin 角色不能編輯 `is_system` 系統角色（若日後 threat model 變更，改為「不允許自我提權」時再做） |
| P2 | 角色 / 權限異動稽核 log（若日後需追溯誤操作） |
