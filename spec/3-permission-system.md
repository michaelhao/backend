# 後台權限系統實作計畫

## Context

目前所有已登入使用者都有相同的存取權限，沒有任何角色或權限機制。需要建立一套 `Module.Action` 格式的權限系統（如 `Dashboard.index`、`Dashboard.detail`），讓管理者可以自由設定每個使用者能存取哪些功能。

## 方案：自建權限系統（不安裝 spatie/laravel-permission）

理由：專案規模小、只有單一 guard、需求明確。避免引入不必要的套件依賴。

**設計原則**：
- 權限檢查全部集中在 Middleware，不使用 Gate::before
- 所有 controller method 統一使用 `#[RequiresPermission]` 宣告所需權限，讓權限定義集中在 method 上
- Middleware 優先讀取 attribute，fallback 為自動推導（開發安全網）
- 移除 `$methodMap`，CRUD aliases（`store→create`、`edit→update`、`destroy→delete`）改由 attribute 明確宣告
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
- `role_id` int → roles
- `permission_id` int → permissions
- PK: `(role_id, permission_id)`

### users 表（修改既有）
- 新增 `role_id` int → roles, nullable
- 使用者只有單一角色，直接用欄位表達，不需 pivot 表

---

## 實作步驟

### Step 1: Migration
- 執行 `php artisan make:migration create_permission_tables --no-interaction`
- 編輯產生的 migration 檔案，建立 permissions、roles、role_has_permissions 共 3 張表

### Step 2: Models
- **新建** `app/Models/Permission.php`
  - 關聯: `roles()`
  - scope: `scopeForModule($query, $module)`
- **新建** `app/Models/Role.php`
  - 關聯: `permissions()`, `users()`
  - 欄位: `default_route` — 對應 `permissions.name`，編輯角色權限時自動加入此權限

### Step 3: HasPermissions Trait
- **新建** `app/Models/Traits/HasPermissions.php`
  - `role(): BelongsTo`（單一角色，透過 `users.role_id`）
  - `hasPermissionTo(string $name): bool` — 從 session 快取讀取權限清單檢查
  - `getDefaultRoute(): ?string` — 取得角色的 default_route（permissions.name 格式）
  - `loadPermissionsToSession(): void` — 從 DB 載入權限清單寫入 session

### Step 4: Migration（修改 users 表）
- 執行 `php artisan make:migration add_role_id_to_users_table --no-interaction`
- 新增 `role_id` int → roles, nullable

### Step 5: 修改 User Model
- **修改** `app/Models/User.php`
- 加入 `use HasPermissions`

### Step 5.1: 登入時載入權限至 Session
- **修改** 登入流程（LoginController 或 Auth event listener）
- 登入成功後呼叫 `$user->loadPermissionsToSession()`
- Session 內儲存 key: `permissions`，值為權限名稱陣列（如 `['Dashboard.index', 'Post.index', ...]`）
- **權限變更時機**：角色或權限異動後，使用者需重新登入才會生效

### Step 6: Middleware（權限檢查的唯一入口）
- **新建** `app/Attributes/RequiresPermission.php`
  - PHP 8 Attribute，宣告 controller method 所需的權限字串
  ```php
  #[\Attribute(\Attribute::TARGET_METHOD)]
  final class RequiresPermission
  {
      public function __construct(public readonly string $permission) {}
  }
  ```
- **新建** `app/Http/Middleware/CheckPermission.php`
- **核心邏輯**：優先讀取 `#[RequiresPermission]` attribute，fallback 為自動推導
  ```php
  public function handle(Request $request, Closure $next): Response
  {
      $user = $request->user();

      $action = $request->route()->getAction();
      if (isset($action['controller'])) {
          [$controller, $method] = explode('@', $action['controller']);
          $className = class_basename($controller);
          $module = str_replace('Controller', '', $className);
          $permissionName = $this->resolvePermission($controller, $method, $module);
      } else {
          abort(403, 'Unauthorized action.');
      }

      if (! $user->role_id) {
          return redirect()->route('no-role');
      }

      if (! $user->hasPermissionTo($permissionName)) {
          $defaultPermission = $user->getDefaultRoute();

          if ($defaultPermission && $defaultPermission !== $permissionName) {
              return redirect()->route($this->permissionToRoute($defaultPermission));
          }

          abort(403, 'Unauthorized action.');
      }

      return $next($request);
  }

  private function resolvePermission(string $controller, string $method, string $module): string
  {
      try {
          $reflectionMethod = new \ReflectionMethod($controller, $method);
          $attributes = $reflectionMethod->getAttributes(RequiresPermission::class);
          if (! empty($attributes)) {
              return $attributes[0]->newInstance()->permission;
          }
      } catch (\ReflectionException) {
          // fallback 自動推導
      }

      return "{$module}.{$method}";
  }
  ```
- `permissionToRoute(string $permissionName): string` — 將 permission name 轉為 route name（使用相同的 `resolvePermission()` 邏輯建立反查 cache）
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
        'Dashboard' => [
            'label' => '儀表板',
            'actions' => ['index' => '首頁', 'detail' => '詳細頁'],
        ],
        'Post' => [
            'label' => '文章',
            'actions' => ['index' => '列表', 'create' => '新增', 'update' => '編輯', 'delete' => '刪除'],
        ],
        'User' => [
            'label' => '使用者',
            'actions' => ['index' => '列表', 'create' => '新增', 'update' => '編輯', 'delete' => '刪除'],
        ],
        'Role' => [
            'label' => '角色',
            'actions' => ['index' => '列表', 'create' => '新增', 'update' => '編輯', 'delete' => '刪除'],
        ],
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
  - 所有 method 加上 `#[RequiresPermission]`
  - `create` 和 `store` 同屬 `Role.create`；`edit` 和 `update` 同屬 `Role.update`
  ```php
  #[RequiresPermission('Role.index')]
  public function index() { ... }

  #[RequiresPermission('Role.create')]
  public function create() { ... }

  #[RequiresPermission('Role.create')]
  public function store(RoleRequest $request) { ... }

  #[RequiresPermission('Role.update')]
  public function edit(Role $role) { ... }

  #[RequiresPermission('Role.update')]
  public function update(RoleRequest $request, Role $role) { ... }

  #[RequiresPermission('Role.delete')]
  public function destroy(Role $role) { ... }
  ```
- **新建** `app/Http/Requests/RoleRequest.php`
  - 驗證 `name` 必填、unique（排除自身）
  - 驗證 `default_route` 必填、存在於 permissions 表
  - 驗證 `permissions` 陣列必填、至少包含 `default_route`
- **新建** `resources/views/roles/index.blade.php` — 角色列表頁
- **新建** `resources/views/roles/create.blade.php` — 新增角色頁
- **新建** `resources/views/roles/edit.blade.php` — 編輯角色頁

### Step 10: Route 整合
- **修改** `routes/web.php`
- `permission` middleware 掛在 group 上；開發用 route 放在 `auth` group 但 `permission` group 外：
  ```php
  Route::middleware('auth')->group(function () {
      Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

      // 無角色提示頁（不受 permission middleware 保護）
      Route::get('/no-role', fn () => view('no-role'))->name('no-role');

      // 開發用 route（只需登入，不需權限）
      Route::get('/test-db', [PostController::class, 'test']);

      // 需要權限檢查的 routes
      Route::middleware('permission')->group(function () {
          Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

          // 角色管理
          Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
          Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
          Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
          Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
          Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
          Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
      });
  });
  ```

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
  - `#[RequiresPermission]` 推斷：`DashboardController@index` → 檢查 `Dashboard.index`
  - `create` 和 `store` 同屬 `Role.create`，有此權限皆可存取
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
讀取 #[RequiresPermission('Dashboard.index')]（fallback: 自動推導）
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

### 新建（17 個）
| 檔案 | 用途 |
|------|------|
| `database/migrations/2026_04_08_xxxxxx_create_permission_tables.php` | permissions、roles、role_has_permissions 資料表 |
| `database/migrations/2026_04_08_xxxxxx_add_role_id_to_users_table.php` | users 表新增 role_id |
| `app/Attributes/RequiresPermission.php` | PHP 8 Attribute，宣告 controller method 所需權限 |
| `app/Models/Permission.php` | Permission Model |
| `app/Models/Role.php` | Role Model |
| `app/Models/Traits/HasPermissions.php` | User 權限 Trait |
| `app/Http/Middleware/CheckPermission.php` | 權限檢查 Middleware（attribute 優先 + fallback 自動推導） |
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
| `routes/web.php` | auth group 內加 permission group + no-role route；開發用 route 移出 permission group |
| `resources/views/layouts/admin.blade.php` | 側邊欄用 `<x-permission>` 包裹 |

### 不變更
| 檔案 | 原因 |
|------|------|
| `composer.json` | 不安裝新套件 |
| `config/auth.php` | 單一 guard 已足夠 |

---

## 新增 Action 的 SOP

新增自定義 action（如 `postRoleInfo`）只需四步：
1. 在 `PermissionSeeder::$modules` 對應 module 的 `actions` 中加入新 action（若是新獨立權限）
2. Controller method 加上 `#[RequiresPermission('Module.action')]`
3. `routes/web.php` 加入對應 route（放在 `permission` middleware group 內）
4. 執行 `php artisan db:seed --class=PermissionSeeder`

---

## 驗證方式

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=Permission
```
