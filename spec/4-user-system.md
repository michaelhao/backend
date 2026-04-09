# 使用者管理系統實作計畫

## Context

權限系統（#3）已完成，使用者現在具備 `role_id` 欄位，且 `PermissionSeeder` 已宣告 `User` 模組的四個動作（index / create / update / delete）。目前後台缺乏使用者管理介面，管理員無法透過 UI 建立、編輯、刪除使用者。本計畫依照現有 Role CRUD 的架構模式，實作 User 管理的操作介面。

**不需要新增 Migration**：`users` 表結構已完整（name、email、password、role_id）。

**不需要修改 PermissionSeeder**：`User.*` 權限已存在。

---

## 架構設計

沿用 RoleController / RoleService / RoleRepository / RoleRequest / Blade Views 的完整分層架構。

### 資料欄位

使用者表單包含以下欄位：

| 欄位 | 說明 |
|------|------|
| name | 必填，string，max:100 |
| email | 必填，email，unique（更新時排除自身） |
| password | 新增時必填，更新時留空則不更改，min:8，需搭配 password_confirmation |
| role_id | 必填，須存在於 roles 表 |

---

## 實作步驟

### Step 1: RoleRepository 新增方法

**修改** `app/Repositories/RoleRepository.php`，新增：

```php
public function getAll(): Collection
{
    return Role::all();
}
```

### Step 2: UserRepository

**新建** `app/Repositories/UserRepository.php`

```php
public function getAllWithRole(): Collection
public function create(array $data): User
public function update(User $user, array $data): void
public function delete(User $user): void
```

說明：
- `getAllWithRole()` — `User::with('role')->latest()->get()`
- `create()` — `User::create($data)`（password 經 model cast 自動 hash）
- `update()` — 直接呼叫 `$user->update($data)`，password 剝除由 Service 層負責

### Step 3: UserService

**新建** `app/Services/UserService.php`

注入 `UserRepository` 與 `RoleRepository`：

```php
/** @return array{users: Collection} */
public function getIndexData(): array       // ['users' => $repo->getAllWithRole()]

/** @return array{roles: Collection} */
public function getCreateData(): array      // ['roles' => $roleRepo->getAll()]

/** @return array{user: User, roles: Collection} */
public function getEditData(User $user): array  // ['user' => $user->load('role'), 'roles' => $roleRepo->getAll()]

public function createUser(array $data): User
public function updateUser(User $user, array $data): void
public function deleteUser(User $user): void
```

說明：
- `updateUser()` — 若 `$data['password']` 為空字串或不存在，先 `unset($data['password'])` 再傳給 repository

### Step 4: UserRequest

**新建** `app/Http/Requests/UserRequest.php`

驗證規則：

```php
'name'     => ['required', 'string', 'max:100'],
'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')?->id)],
// 使用 $this->route('user') 取得路由參數，與 RoleRequest 慣例一致
// 注意：$this->user 是 FormRequest 的 authenticated user，不是路由參數
'password' => [$this->isMethod('POST') ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
'role_id'  => ['required', 'exists:roles,id'],
```

### Step 5: UserController

**新建** `app/Http/Controllers/UserController.php`

```php
#[RequiresPermission('User.index')]
public function index()

#[RequiresPermission('User.create')]
public function create()

#[RequiresPermission('User.create')]
public function store(UserRequest $request)

#[RequiresPermission('User.update')]
public function edit(User $user)

#[RequiresPermission('User.update')]
public function update(UserRequest $request, User $user)

#[RequiresPermission('User.delete')]
public function destroy(User $user)
```

特殊邏輯：
- `destroy()` — 若 `$user->id === auth()->id()` 則 redirect with error `'無法刪除自己的帳號'`

### Step 6: Routes

**修改** `routes/web.php`，在 `permission` middleware group 內新增（與角色路由並列）：

```php
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
```

### Step 7: Blade Views

**新建** `resources/views/admin/users/` 目錄，包含以下檔案：

#### `index.blade.php`

- 表格欄位：名稱 / 電子郵件 / 角色 / 操作
- `<x-permission name="User.create">` 包裹「新增使用者」按鈕
- `<x-permission name="User.update">` 包裹「編輯」連結
- `<x-permission name="User.delete">` 包裹「刪除」form（含 confirm 確認）
- 閃現訊息（success / error）+ 自動淡出 script（同 roles/index.blade.php）

#### `create.blade.php`

```blade
@include('admin.users._form', [
    'action' => route('users.store'),
    'method' => 'POST',
    'submitLabel' => '建立使用者',
    'user' => null,
])
```

#### `edit.blade.php`

```blade
@include('admin.users._form', [
    'action' => route('users.update', $user),
    'method' => 'PUT',
    'submitLabel' => '儲存變更',
])
```

`$user` 由父視圖作用域自動帶入（`getEditData` 回傳）。

#### `_form.blade.php`

表單欄位：
- `name` — text input，`value="{{ old('name', $user->name ?? '') }}"`
- `email` — email input，`value="{{ old('email', $user->email ?? '') }}"`
- `password` — password input（edit 頁顯示 placeholder：留空則不修改）
- `password_confirmation` — password input
- `role_id` — `<select>` 從 `$roles` 產生選項，`selected` 判斷 `old('role_id', $user->role_id ?? '')`
- 送出按鈕與取消連結（`route('users.index')`）

### Step 8: 側邊欄導覽

**修改** `resources/views/layouts/admin.blade.php`

側邊欄已有 `<x-permission name="User.index">` 區塊（`href="#"`），僅需更新連結並加入 active state：

```blade
<x-permission name="User.index">
    <a href="{{ route('users.index') }}"
       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
              {{ request()->routeIs('users.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
        使用者管理
    </a>
</x-permission>
```

### Step 9: 測試

**新建** `tests/Feature/UserCrudTest.php`

測試情境：
- Admin 可存取 `/users`（200）
- Admin 可新增使用者（含角色），資料庫確認建立
- Admin 可編輯使用者（不填密碼時，密碼不變）
- Admin 可編輯使用者（填入新密碼時，密碼更新）
- Edit 表單載入已有值（name、email、role_id 正確預填）
- Admin 可刪除其他使用者
- Admin 刪除自己 → redirect with error
- Viewer 存取 `/users/create` → redirect（無 create 權限）
- 建立使用者時 email 重複 → 驗證錯誤
- 建立使用者時未填 password → 驗證錯誤（required on POST）
- 建立使用者時 password 與 password_confirmation 不一致 → 驗證錯誤
- 編輯使用者時 email 改為已存在的其他使用者 email → 驗證錯誤
- 建立使用者時 role_id 不存在 → 驗證錯誤

---

## 檔案清單

### 新建（9 個）

| 檔案 | 用途 |
|------|------|
| `app/Repositories/UserRepository.php` | 使用者資料存取層 |
| `app/Services/UserService.php` | 使用者業務邏輯層 |
| `app/Http/Requests/UserRequest.php` | 使用者表單驗證 |
| `app/Http/Controllers/UserController.php` | 使用者管理 CRUD Controller |
| `resources/views/admin/users/index.blade.php` | 使用者列表頁 |
| `resources/views/admin/users/create.blade.php` | 新增使用者頁 |
| `resources/views/admin/users/edit.blade.php` | 編輯使用者頁 |
| `resources/views/admin/users/_form.blade.php` | 共用表單元件 |
| `tests/Feature/UserCrudTest.php` | 使用者 CRUD 功能測試 |

### 修改（3 個）

| 檔案 | 變更 |
|------|------|
| `app/Repositories/RoleRepository.php` | 新增 `getAll(): Collection` |
| `routes/web.php` | 在 permission group 內加入 6 條使用者路由 |
| `resources/views/layouts/admin.blade.php` | 側邊欄使用者管理連結 `href` 從 `#` 改為 `route('users.index')` + 加入 active state |

### 不變更

| 檔案 | 原因 |
|------|------|
| `database/migrations/` | users 表結構已完整，無需新增欄位 |
| `database/seeders/PermissionSeeder.php` | User.* 權限已定義 |
| `app/Models/User.php` | 現有 fillable / casts / trait 已足夠 |

---

## 驗證方式

```bash
php artisan route:list --path=users
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=UserCrud
```
