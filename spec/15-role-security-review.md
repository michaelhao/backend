# 角色管理安全審查與補強

## 背景說明

針對既有角色管理流程做一次完整的安全性審查（自製權限系統、Role/Permission/User 三表、attribute-based middleware、session 快取權限）。整體架構分層清楚、Form Request / Repository / Service 都到位，但有幾項缺口需補上：

- session 中的權限只在登入時載入一次，管理者異動角色權限後**已登入使用者不會即時生效**，需登出/過期才會更新
- Remember-me 自動登入未走 `AuthService::loginSession()` → session 完全沒有 `permissions` key，使用者體感「整個權限系統壞掉」
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

---

## 需修改的檔案

| 檔案 | 修改內容 |
|---|---|
| `app/Http/Middleware/CheckPermission.php` | 改用 `PermissionRouteResolver`；加入 session 版本戳檢查，stale 時自動 reload 權限；移除原本的 static cache |
| `app/Models/Traits/HasPermissions.php` | session key 改為 `auth.permissions` / `auth.permissions_version`；新增 `currentPermissionsVersion()`、`permissionsSessionIsStale()` |
| `app/Repositories/RoleRepository.php` | `syncPermissions()` 內呼叫 `$role->touch()` 更新版本戳；`getByName` → `findByNameOrFail` |
| `app/Providers/AppServiceProvider.php` | 註冊 `PermissionRouteResolver` singleton；`Authenticated` event listener 涵蓋 remember-me |
| `app/Http/Requests/RoleRequest.php` | `default_route` 加自訂 rule 確認對應到實際命名路由 |
| `tests/Feature/PermissionTest.php` | 新增 3 個測試 |

---

## 實作細節

### 1. Session 權限即時撤銷 + Remember-me 修補（P2）

**問題**
- `loadPermissionsToSession()` 僅在 `AuthService::loginSession()` 中被呼叫；管理者修改 Role 權限或使用者 `role_id` 後，已登入者 session 內權限不會更新。
- Remember-me 自動登入沒走 `attempt() → loginSession()`，session 中沒有 `permissions` key → middleware 一律判定無權限。

**設計重點**
- 用 `max(users.updated_at, roles.updated_at).timestamp` 當作版本戳，**不需新增欄位、不需 migration**。
- 異動 role permissions 時於 `RoleRepository::syncPermissions()` 內 `$role->touch()`；異動 `users.role_id` 時 Eloquent 會自動 touch `users.updated_at`。
- middleware 在每個 protected request 入口比對 session 版本戳與 DB 當前版本戳，不一致就 reload。
- 額外註冊 `Illuminate\Auth\Events\Authenticated` listener 作為 defense-in-depth，涵蓋 remember-me 路徑。

**`HasPermissions` trait 重點**

```php
public function loadPermissionsToSession(): void
{
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

**`AppServiceProvider` 重點**

```php
public function register(): void
{
    $this->app->singleton(PermissionRouteResolver::class);
}

public function boot(): void
{
    Event::listen(Authenticated::class, function (Authenticated $event): void {
        $user = $event->user;

        if (! in_array(HasPermissions::class, class_uses_recursive($user), true)) {
            return;
        }

        if ($user->permissionsSessionIsStale()) {
            $user->loadPermissionsToSession();
        }
    });
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

```php
private function defaultRouteResolvableRule(): ValidationRule
{
    $resolver = app(PermissionRouteResolver::class);

    return new class($resolver) implements ValidationRule
    {
        public function __construct(private PermissionRouteResolver $resolver) {}

        public function validate(string $attribute, mixed $value, \Closure $fail): void
        {
            if (! is_string($value) || $value === '') {
                return;
            }

            if ($this->resolver->routeNameFor($value) === null) {
                $fail('所選的預設頁面尚未對應到任何路由。');
            }
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

`PermissionTest` 新增 3 個測試（共 21 個全綠）：

- `test_role_request_rejects_default_route_without_named_route`
  建立一個存在於 `permissions` 但無對應 route 的 permission，POST `/roles` 設為 `default_route` → `assertSessionHasErrors('default_route')`。
- `test_permission_session_reloads_when_role_updated_at_advances`
  使用者已可訪問 `/roles`；管理者拔掉該角色的 `Role.index` 權限並 advance `roles.updated_at` → 同 session 再次請求應自動 reload，導向 default_route。
- `test_session_without_loaded_permissions_is_auto_loaded`
  模擬 remember-me：`actingAs` 後手動 `forget('auth.permissions')` → 仍應能正常進入受保護的頁面。

### 手動

1. 兩個瀏覽器：A 用 Admin 登入、B 用 Viewer 登入。
2. A 把 Viewer 角色的 `Dashboard.index` 之外的權限全拿掉。
3. B 重新整理任意管理頁面 → 應立即被導回 dashboard，無需登出。
4. 勾選「記住我」登入 → 關閉瀏覽器後重開 → 直接進到任一管理頁，菜單與權限正常。
5. 嘗試在角色編輯頁把 `default_route` 設為一個沒有對應命名路由的 permission（需先手動建一個 orphan permission）→ 表單應報錯。

---

## 影響範圍

- **行為改變**：管理者異動角色權限後，受影響使用者**下一次 request** 即生效（原本要登出 / session 過期）。
- **行為改變**：Remember-me 自動登入後權限正常運作（原本可能整個壞掉）。
- **行為改變**：`default_route` 設定時會多一層命名路由存在性驗證。
- **無 schema 變動**：不需 migration，沿用既有 `updated_at` + `$role->touch()`。
- **每個 protected request 多 2 個輕量 SELECT**（取 `users.updated_at` 與 `roles.updated_at`）— 內部後台流量可接受。

---

## 後續可做（不在本次 scope）

| 等級 | 項目 |
|---|---|
| P3 | RoleController 改用 route model binding，把 `Role::find($id) + redirect-with-flash` 樣板集中到全域 missing handler |
| P2 | `users.role_id` 加 foreign key 約束 |
| P2 | 限制非 Admin 角色不能編輯 `is_system` 系統角色（若日後 threat model 變更，改為「不允許自我提權」時再做） |
| P2 | 角色 / 權限異動稽核 log（若日後需追溯誤操作） |
