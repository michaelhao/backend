# 路由改為手動 ID 查詢

## Context

原本 roles、grades、users、shops 四個資源的 edit / update / destroy（或 certify / toggleStatus）路由均使用 Laravel **Route Model Binding**（`{role}`、`{grade}`、`{user}`、`{shop}`），找不到資料時自動回傳 404。

改為手動傳 `{id}`，由 Controller 統一負責查詢與錯誤處理：
- 找得到 → 繼續執行原有邏輯
- 找不到 → `redirect()->route('*.index')->with('error', '...')`

好處：
- 錯誤導向邏輯集中於 Controller，清楚易讀
- 回應語意更貼近應用（導回列表 + 錯誤訊息，而非空白 404 頁）

---

## 變更範圍

### `routes/web.php`

12 條路由，路由參數從資源名稱改為 `{id}`：

```php
// 角色管理
Route::get('/roles/{id}/edit',    [RoleController::class, 'edit'])->name('roles.edit');
Route::put('/roles/{id}',         [RoleController::class, 'update'])->name('roles.update');
Route::delete('/roles/{id}',      [RoleController::class, 'destroy'])->name('roles.destroy');

// 等級管理
Route::get('/grades/{id}/edit',   [GradeController::class, 'edit'])->name('grades.edit');
Route::put('/grades/{id}',        [GradeController::class, 'update'])->name('grades.update');
Route::patch('/grades/{id}/toggle',[GradeController::class, 'toggleStatus'])->name('grades.toggle');

// 使用者管理
Route::get('/users/{id}/edit',    [UserController::class, 'edit'])->name('users.edit');
Route::put('/users/{id}',         [UserController::class, 'update'])->name('users.update');
Route::delete('/users/{id}',      [UserController::class, 'destroy'])->name('users.destroy');

// 商店管理
Route::get('/shops/{id}/edit',    [ShopController::class, 'edit'])->name('shops.edit');
Route::put('/shops/{id}',         [ShopController::class, 'update'])->name('shops.update');
Route::post('/shops/{id}/certify',[ShopController::class, 'certify'])->name('shops.certify');
```

### Controller 統一模式

每個受影響的方法改為：

```php
public function edit($id)
{
    $model = ModelClass::find($id);
    if (!$model) {
        return redirect()->route('resource.index')->with('error', '找不到該...');
    }
    // 原有邏輯不變，$model 取代原本的 $typehintedParam
}
```

| Controller | 方法 | 找不到時的錯誤訊息 |
|---|---|---|
| `RoleController` | edit, update, destroy | `找不到該角色` |
| `GradeController` | edit, update, toggleStatus | `找不到該方案` |
| `UserController` | edit, update, destroy | `找不到該使用者` |
| `ShopController` | edit, update, certify | `找不到該商店` |

### 不受影響的部分

- **Views**：`route('shops.edit', $shop)` 傳入 Model 實例時，Laravel 自動解析為 `id`，路由參數改名不影響 URL 生成，無需修改。
- **Services / Repositories**：仍接收 Model 實例，Controller 查到後傳入，介面不變。
- **ShopUpdateRequest**：原本使用 `$this->route('shop')->id` 做 unique ignore，需同步改為 `$this->route('id')`。

---

## 修改檔案

| 檔案 | 變更 |
|------|------|
| `routes/web.php` | 12 條路由參數改為 `{id}` |
| `app/Http/Controllers/RoleController.php` | edit, update, destroy |
| `app/Http/Controllers/GradeController.php` | edit, update, toggleStatus |
| `app/Http/Controllers/UserController.php` | edit, update, destroy |
| `app/Http/Controllers/ShopController.php` | edit, update, certify |

---

## 驗證方式

對每個資源測試：

1. 存在的 id（如 `/roles/1/edit`）→ 正常顯示編輯頁
2. 不存在的 id（如 `/roles/9999/edit`）→ 導回對應 index，顯示 error flash 訊息
3. Index 頁的編輯連結仍正確（views 不需改）

```bash
php artisan route:list --path=roles
php artisan route:list --path=grades
php artisan route:list --path=users
php artisan route:list --path=shops
```
