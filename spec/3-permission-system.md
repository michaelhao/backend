# 後台權限系統實作計畫

## Context

目前所有已登入使用者都有相同的存取權限，沒有任何角色或權限機制。需要建立一套 `Module.Action` 格式的權限系統（如 `Dashboard.index`、`Dashboard.detail`），讓管理者可以自由設定每個使用者能存取哪些功能。

## 方案：自建權限系統（不安裝 spatie/laravel-permission）

理由：專案規模小、只有單一 guard、需求明確。避免引入不必要的套件依賴。

**設計原則**：
- 權限檢查全部集中在 Middleware，不使用 Gate::before
- Middleware **自動從 Controller 推斷權限名稱**，不需手動指定
- 使用者只有**單一角色**
- 沒有權限時**導向角色的預設頁面**，而非回 403

---

## 資料庫設計（2 個 Migration）

使用 `php artisan make:migration create_permission_tables` 建立，會自動產生時間戳記。

**檔案**: `database/migrations/2026_04_08_xxxxxx_create_permission_tables.php`

### permissions 表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint PK | |
| module | string(50) | 模組名稱，如 `Dashboard` |
| action | string(50) | 動作名稱，如 `index` |
| name | string(100), unique | 組合鍵 `Module.action` |
| description | string, nullable | 說明 |
| created_at | datetime | |
| updated_at | datetime | |

### roles 表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint PK | |
| name | string(100), unique | 角色名稱，如 `Admin` |
| description | string, nullable | 說明 |
| default_route | string(100) | 對應 `permissions.name`，如 `Dashboard.index`。無權限時導向此頁面 |
| created_at | datetime | |
| updated_at | datetime | |

### role_has_permissions（pivot）
- `role_id` FK → roles
- `permission_id` FK → permissions
- PK: `(role_id, permission_id)`

### users 表（修改既有）
- 新增 `role_id` FK → roles, nullable
- 使用者只有單一角色，直接用欄位表達，不需 pivot 表

---

## 實作步驟

### Step 1: Migration
- 執行 `php artisan make:migration create_permission_tables --no-interaction`
- 編輯產生的 migration 檔案，建立 permissions、roles、role_has_permissions 共 3 張表

### Step 2: Models
- **新建** `app/Models/Permission.php`
  - 關聯: `roles()`
  - boot 時自動組合 `name = "{$module}.{$action}"`
  - scope: `scopeForModule($query, $module)`
- **新建** `app/Models/Role.php`
  - 關聯: `permissions()`, `users()`
  - 方法: `hasPermission(string $name): bool`
  - 欄位: `default_route` — 對應 `permissions.name`，編輯角色權限時自動加入此權限

### Step 3: HasPermissions Trait
- **新建** `app/Models/Traits/HasPermissions.php`
  - `role(): BelongsTo`（單一角色，透過 `users.role_id`）
  - `hasPermissionTo(string $name): bool` — 從 session 快取讀取權限清單檢查
  - `hasRole(string $name): bool`
  - `assignRole(string|Role $role): void` — 更新 `role_id`，同時清除 session 權限快取
  - `getDefaultRoute(): ?string` — 取得角色的 default_route（permissions.name 格式）
  - `loadPermissionsToSession(): void` — 從 DB 載入權限清單寫入 session
  - `clearPermissionCache(): void` — 清除 session 中的權限快取

### Step 4: Migration（修改 users 表）
- 執行 `php artisan make:migration add_role_id_to_users_table --no-interaction`
- 新增 `role_id` FK → roles, nullable

### Step 5: 修改 User Model
- **修改** `app/Models/User.php`
- 加入 `use HasPermissions`

### Step 5.1: 登入時載入權限至 Session
- **修改** 登入流程（LoginController 或 Auth event listener）
- 登入成功後呼叫 `$user->loadPermissionsToSession()`
- Session 內儲存 key: `permissions`，值為權限名稱陣列（如 `['Dashboard.index', 'Post.index', ...]`）
- **權限變更時機**：角色或權限異動後，使用者需重新登入才會生效

### Step 6: Middleware（權限檢查的唯一入口，自動推斷權限）
- **新建** `app/Http/Middleware/CheckPermission.php`
- **核心邏輯**：從 route 的 Controller 自動推斷權限名稱
  - `DashboardController@index` → `Dashboard.index`
  - `PostController@create` → `Post.create`
  - 規則：Controller 名稱去掉 `Controller` 後綴 = module，method 名稱 = action
  ```php
  public function handle(Request $request, Closure $next): Response
  {
      $user = $request->user();

      // 從 route 的 controller 和 method 推斷權限名稱
      $action = $request->route()->getAction();
      if (isset($action['controller'])) {
          [$controller, $method] = explode('@', $action['controller']);
          $className = class_basename($controller);
          $module = str_replace('Controller', '', $className);
          $permissionName = "{$module}.{$method}";
      } else {
          abort(403, 'Unauthorized action.'); // 非 controller route（closure）預設拒絕
      }

      // 無角色 → 導向無角色提示頁
      if (! $user->role_id) {
          return redirect()->route('no-role');
      }

      if (! $user->hasPermissionTo($permissionName)) {
          $defaultPermission = $user->getDefaultRoute(); // 回傳 permissions.name，如 'Dashboard.index'

          // 避免重複導向迴圈
          if ($defaultPermission && $defaultPermission !== $permissionName) {
              return redirect()->route($this->permissionToRoute($defaultPermission));
          }

          abort(403, 'Unauthorized action.');
      }

      return $next($request);
  }
  ```
- `permissionToRoute(string $permissionName): string` — 將 permission name 轉為 route name
  - `Dashboard.index` → route name 透過反查 route 表，找到對應 `DashboardController@index` 的 route name
- **修改** `bootstrap/app.php`
  - 在 `withMiddleware` 內加入 alias: `'permission' => CheckPermission::class`

### Step 7: Blade Component（UI 層控制）
- **新建** `app/View/Components/Permission.php`
  - 不需要在任何 Service Provider 註冊，Laravel 自動發現
  ```php
  class Permission extends Component
  {
      public function __construct(public string $name) {}

      public function shouldRender(): bool
      {
          return auth()->check() && auth()->user()->hasPermissionTo($this->name);
      }

      public function render(): View
      {
          return view('components.permission');
      }
  }
  ```
- **新建** `resources/views/components/permission.blade.php`
  ```blade
  {{ $slot }}
  ```

### Step 8: Seeder
- **新建** `database/seeders/PermissionSeeder.php`
  - 宣告式 `$modules` 陣列定義所有模組與動作：
    ```php
    private array $modules = [
        'Dashboard' => ['index', 'detail'],
        'Post'      => ['index', 'create', 'update', 'delete'],
        'User'      => ['index', 'create', 'update', 'delete'],
        'Role'      => ['index', 'create', 'update', 'delete'],
    ];
    ```
  - 建立 `Admin` 角色（全部權限，default_route: `Dashboard.index`）
  - 建立 `Viewer` 角色（僅 index 權限，default_route: `Dashboard.index`）
  - 角色建立時驗證 `default_route` 對應的 permission 存在
  - **規則**：角色的 `default_route` 權限在編輯角色權限時會自動加入，確保角色一定擁有其預設頁面的權限
  - 使用 `firstOrCreate` 確保可重複執行
  - **Sync 機制**：移除不在 `$modules` 陣列中的舊權限，並清理相關的 `role_has_permissions` 關聯
- **修改** `database/seeders/DatabaseSeeder.php`
  - 呼叫 `PermissionSeeder`
  - 測試帳號指定 `Admin` 角色

### Step 9: 角色管理頁面（CRUD）
- **新建** `app/Http/Controllers/RoleController.php`
  - `index` — 角色列表，顯示每個角色的名稱、權限數量、使用者數量
  - `create` — 新增角色表單，可選擇權限（以模組分組顯示 checkbox）
  - `store` — 儲存新角色，驗證 `default_route` 對應的權限已被勾選，自動加入 `default_route` 權限
  - `update` — 更新角色名稱、權限、`default_route`，自動加入 `default_route` 權限
  - `delete` — 刪除角色前檢查是否仍有使用者使用該角色
- **新建** `app/Http/Requests/RoleRequest.php`
  - 驗證 `name` 必填、unique（排除自身）
  - 驗證 `default_route` 必填、存在於 permissions 表
  - 驗證 `permissions` 陣列必填、至少包含 `default_route`
- **新建** `resources/views/roles/index.blade.php` — 角色列表頁
- **新建** `resources/views/roles/create.blade.php` — 新增角色頁
- **新建** `resources/views/roles/edit.blade.php` — 編輯角色頁

### Step 10: Route 整合
- **修改** `routes/web.php`
- `permission` middleware 掛在 group 上，不需要每條 route 手動指定權限：
  ```php
  Route::middleware('auth')->group(function () {
      Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

      // 無角色提示頁（不受 permission middleware 保護）
      Route::get('/no-role', fn () => view('no-role'))->name('no-role');

      // 需要權限檢查的 routes — middleware 自動推斷權限
      Route::middleware('permission')->group(function () {
          Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

          // 角色管理
          Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
          Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
          Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
          Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
          Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
          Route::delete('/roles/{role}', [RoleController::class, 'delete'])->name('roles.delete');

          // 未來新增的 routes 自動受權限保護
      });
  });
  ```
- `logout` 和 `no-role` 放在 `permission` group 外面，所有登入使用者都能存取

### Step 11: Blade 整合
- **修改** `resources/views/layouts/admin.blade.php`
  - 側邊欄連結用 `<x-permission>` 包裹：
  ```blade
  <x-permission name="Dashboard.index">
      <a href="{{ route('dashboard') }}">Dashboard</a>
  </x-permission>

  <x-permission name="Post.index">
      <a href="#">Posts</a>
  </x-permission>

  <x-permission name="Role.index">
      <a href="{{ route('roles.index') }}">Roles</a>
  </x-permission>
  ```

### Step 12: 測試
- **新建** `tests/Feature/PermissionTest.php`
  - 無角色使用者 → 被導向無角色提示頁（`no-role`）
  - 無權限使用者 → 被導向預設頁面（redirect）
  - Admin 角色 → 200
  - Viewer 角色 → 僅 index 可存取，其他被 redirect
  - 預設頁面也無權限 → 403
  - 自動推斷：DashboardController@index → 檢查 Dashboard.index
  - Closure route 在 permission group 內 → 403
  - 角色 CRUD：新增、編輯、刪除角色正常運作
  - 編輯角色時 `default_route` 權限自動加入
  - 刪除仍有使用者的角色 → 拒絕

---

## 架構流程圖

```
使用者 Request → GET /
    ↓
Route: middleware(['auth', 'permission'])
    ↓
CheckPermission Middleware
    ↓
Closure route? → abort(403)
    ↓
無角色（role_id = null）? → redirect 至 no-role 提示頁
    ↓
自動推斷：DashboardController@index → "Dashboard.index"
    ↓
$user->hasPermissionTo('Dashboard.index')           ← HasPermissions Trait
    ↓                                                  (從 session 快取讀取，不查 DB)
通過 → 放行
    ↓
不通過 → redirect 至角色預設頁面（default_route 對應的 route）
    ↓
已在預設頁面或無預設頁面 → 403

Blade 側邊欄：
<x-permission name="Dashboard.index">               ← Blade Component
    ↓                                                  (shouldRender → hasPermissionTo)
有權限 → 顯示  /  無權限 → 隱藏
```

---

## 檔案清單

### 新建（16 個）
| 檔案 | 用途 |
|------|------|
| `database/migrations/2026_04_08_xxxxxx_create_permission_tables.php` | permissions、roles、role_has_permissions 資料表 |
| `database/migrations/2026_04_08_xxxxxx_add_role_id_to_users_table.php` | users 表新增 role_id FK |
| `app/Models/Permission.php` | Permission Model |
| `app/Models/Role.php` | Role Model |
| `app/Models/Traits/HasPermissions.php` | User 權限 Trait |
| `app/Http/Middleware/CheckPermission.php` | 權限檢查 Middleware（自動推斷 + 無角色導向 + 導向預設頁面） |
| `app/Http/Controllers/RoleController.php` | 角色管理 CRUD Controller |
| `app/Http/Requests/RoleRequest.php` | 角色表單驗證（含 default_route 權限檢查） |
| `app/View/Components/Permission.php` | Blade Component（UI 層權限控制） |
| `resources/views/components/permission.blade.php` | Component 模板（僅 `{{ $slot }}`） |
| `resources/views/no-role.blade.php` | 無角色提示頁（請聯絡管理員） |
| `resources/views/roles/index.blade.php` | 角色列表頁 |
| `resources/views/roles/create.blade.php` | 新增角色頁 |
| `resources/views/roles/edit.blade.php` | 編輯角色頁 |
| `database/seeders/PermissionSeeder.php` | 預設權限與角色 Seeder（含 sync 機制） |
| `tests/Feature/PermissionTest.php` | 權限功能測試 |

### 修改（5 個）
| 檔案 | 變更 |
|------|------|
| `app/Models/User.php` | 加入 `use HasPermissions` |
| `app/Http/Controllers/LoginController.php` | 登入成功後呼叫 `loadPermissionsToSession()` |
| `bootstrap/app.php` | 註冊 `permission` middleware alias |
| `routes/web.php` | auth group 內加 permission group + no-role route |
| `resources/views/layouts/admin.blade.php` | 側邊欄用 `<x-permission>` 包裹 |

### 不變更
| 檔案 | 原因 |
|------|------|
| `composer.json` | 不安裝新套件 |
| `config/auth.php` | 單一 guard 已足夠 |

---

## 未來擴充方式

新增模組只需兩步：
1. 在 `PermissionSeeder` 的 `$modules` 陣列加入新模組 → 跑 seed
2. 新 route 放進 `permission` middleware group 即可，middleware 自動推斷權限

---

## 驗證方式

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=Permission
```
