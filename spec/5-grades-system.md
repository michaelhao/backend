# 版本管理系統實作計畫

## Context

使用者系統（#4）已完成，系統目前具備角色與權限管理、使用者管理功能。為支援商品或使用者的分級需求，本計畫新增 **版本（Grade）** 管理功能，共預設 S / A / B / C / D / E 六個版本。

與其他資源不同，版本**不支援刪除**，僅透過 `status` 欄位切換啟用／關閉，確保版本資料的參照穩定性。本計畫亦引入專案第一個 **PHP Backed Enum**（`GradeStatus`）作為 status 的型別安全機制，並在 FormRequest 中以 `Enum` 規則驗證輸入。

---

## 架構設計

沿用 Controller / Service / Repository / Request / Blade Views 的完整分層架構（比照 UserController 系列）。

### 資料欄位

| 欄位 | 型別 | 說明 |
|------|------|------|
| id | PK bigint | |
| code | varchar(30) unique | 僅限中英數，grades 表內不重複 |
| name | varchar(30) unique | 僅限中英數，grades 表內不重複 |
| price | int | 必須大於 1 |
| status | tinyint | Model cast 為 `GradeStatus` Enum（1=啟用, 0=關閉） |
| created_at | datetime | |
| updated_at | datetime | |

### GradeStatus Enum

```php
namespace App\Enums;

enum GradeStatus: int
{
    case Active   = 1;
    case Inactive = 0;
}
```

### 預設版本資料

| name | code | price | status |
|------|------|-------|--------|
| 版本S | grade_s | 10000 | 1 |
| 版本A | grade_a | 9000 | 1 |
| 版本B | grade_b | 8000 | 1 |
| 版本C | grade_c | 7000 | 1 |
| 版本D | grade_d | 6000 | 1 |
| 版本E | grade_e | 5000 | 1 |

---

## 實作步驟

### Step 1: Migration

**新建** `database/migrations/YYYY_MM_DD_HHMMSS_create_grades_table.php`

```php
Schema::create('grades', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('name', 30)->unique();
    $table->unsignedInteger('price');
    $table->tinyInteger('status')->default(1);
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
```

### Step 2: GradeStatus Enum

**新建** `app/Enums/GradeStatus.php`

```php
namespace App\Enums;

enum GradeStatus: int
{
    case Active   = 1;
    case Inactive = 0;
}
```

### Step 3: Grade Model

**新建** `app/Models/Grade.php`

```php
namespace App\Models;

use App\Enums\GradeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'price', 'status'];

    protected function casts(): array
    {
        return [
            'status' => GradeStatus::class,
        ];
    }
}
```

### Step 4: GradeFactory

**新建** `database/factories/GradeFactory.php`

供測試建立假資料使用：

```php
public function definition(): array
{
    return [
        'code'   => 'grade_' . fake()->unique()->lexify('???'),
        'name'   => '版本' . fake()->unique()->lexify('???'),
        'price'  => fake()->numberBetween(2, 99999),
        'status' => GradeStatus::Active,
    ];
}
```

### Step 5: GradeSeeder

**新建** `database/seeders/GradeSeeder.php`

使用 `updateOrCreate` 保持冪等性，以 `code` 為唯一鍵：

```php
$grades = [
    ['name' => '版本S', 'code' => 'grade_s', 'price' => 10000],
    ['name' => '版本A', 'code' => 'grade_a', 'price' => 9000],
    ['name' => '版本B', 'code' => 'grade_b', 'price' => 8000],
    ['name' => '版本C', 'code' => 'grade_c', 'price' => 7000],
    ['name' => '版本D', 'code' => 'grade_d', 'price' => 6000],
    ['name' => '版本E', 'code' => 'grade_e', 'price' => 5000],
];

foreach ($grades as $grade) {
    Grade::updateOrCreate(
        ['code' => $grade['code']],
        ['name' => $grade['name'], 'price' => $grade['price'], 'status' => GradeStatus::Active->value],
    );
}
```

**修改** `database/seeders/DatabaseSeeder.php` — 在 `PermissionSeeder` 之後呼叫：

```php
$this->call(GradeSeeder::class);
```

### Step 6: PermissionSeeder — 新增 Grade 模組

**修改** `database/seeders/PermissionSeeder.php`

在 `$modules` 陣列新增（**無 delete action**）：

```php
'Grade' => [
    'label'   => '版本',
    'actions' => [
        'index'  => '列表',
        'create' => '新增',
        'update' => '編輯',
    ],
],
```

### Step 7: GradeRepository

**新建** `app/Repositories/GradeRepository.php`

```php
public function getAll(): Collection           // Grade::latest()->get()
public function create(array $data): Grade     // Grade::create($data)
public function update(Grade $grade, array $data): void  // $grade->update($data)
public function toggleStatus(Grade $grade): void
// $grade->update(['status' => $grade->status === GradeStatus::Active
//     ? GradeStatus::Inactive
//     : GradeStatus::Active])
```

### Step 8: GradeService

**新建** `app/Services/GradeService.php`

注入 `GradeRepository`：

```php
public function getIndexData(): array                    // ['grades' => $repo->getAll()]
public function getCreateData(): array                   // [] （建立表單不需額外資料）
public function getEditData(Grade $grade): array         // ['grade' => $grade]
public function createGrade(array $data): Grade
public function updateGrade(Grade $grade, array $data): void
public function toggleStatus(Grade $grade): void         // 委派給 $repo->toggleStatus($grade)
```

### Step 9: GradeRequest

**新建** `app/Http/Requests/GradeRequest.php`

```php
'code'   => [
    'required', 'string', 'max:30',
    'regex:/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u',
    Rule::unique('grades', 'code')->ignore($this->route('grade')?->id),
],
'name'   => [
    'required', 'string', 'max:30',
    'regex:/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u',
    Rule::unique('grades', 'name')->ignore($this->route('grade')?->id),
],
'price'  => ['required', 'integer', 'min:2'],
'status' => ['required', new Enum(GradeStatus::class)],
```

### Step 10: GradeController

**新建** `app/Http/Controllers/GradeController.php`

CRU 方法（**無 destroy**）：

```php
#[RequiresPermission('Grade.index')]
public function index()

#[RequiresPermission('Grade.create')]
public function create()

#[RequiresPermission('Grade.create')]
public function store(GradeRequest $request)
// → redirect()->route('grades.index')->with('success', '版本已建立')

#[RequiresPermission('Grade.update')]
public function edit(Grade $grade)

#[RequiresPermission('Grade.update')]
public function update(GradeRequest $request, Grade $grade)
// → redirect()->route('grades.index')->with('success', '版本已更新')

#[RequiresPermission('Grade.update')]
public function toggleStatus(Grade $grade)
// 切換 status：Active ↔ Inactive
// → redirect()->route('grades.index')->with('success', '版本狀態已更新')
```

### Step 11: Routes

**修改** `routes/web.php` — 在 `permission` middleware group 內新增（**無 delete 路由**）：

```php
// 版本管理
Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
Route::get('/grades/create', [GradeController::class, 'create'])->name('grades.create');
Route::post('/grades', [GradeController::class, 'store'])->name('grades.store');
Route::get('/grades/{grade}/edit', [GradeController::class, 'edit'])->name('grades.edit');
Route::put('/grades/{grade}', [GradeController::class, 'update'])->name('grades.update');
Route::patch('/grades/{grade}/toggle', [GradeController::class, 'toggleStatus'])->name('grades.toggle');
```

### Step 12: Blade Views

**新建** `resources/views/admin/grades/` 目錄，包含：

#### `index.blade.php`
- 表格欄位：代碼 / 名稱 / 價格 / 狀態 / 操作
- 狀態欄：`<x-permission name="Grade.update">` 包裹切換表單
  - 以 toggle switch 元件呈現（啟用：綠色，關閉：灰色）
  - 點擊後提交 `PATCH /grades/{grade}/toggle`（`@method('PATCH')`）
  - 無權限時顯示純 badge（不可點擊）
- `<x-permission name="Grade.create">` 包裹「新增版本」按鈕
- `<x-permission name="Grade.update">` 包裹「編輯」連結
- 閃現訊息（success / error）+ 自動淡出 script

#### `create.blade.php`
```blade
@include('admin.grades._form', [
    'action'      => route('grades.store'),
    'method'      => 'POST',
    'submitLabel' => '建立版本',
    'grade'       => null,
])
```

#### `edit.blade.php`
```blade
@include('admin.grades._form', [
    'action'      => route('grades.update', $grade),
    'method'      => 'PUT',
    'submitLabel' => '儲存變更',
])
```

#### `_form.blade.php`
- `code` — text input，`value="{{ old('code', $grade->code ?? '') }}"`
- `name` — text input，`value="{{ old('name', $grade->name ?? '') }}"`
- `price` — number input，`value="{{ old('price', $grade->price ?? '') }}"`
- `status` — `<select>` 選項：啟用（value=1）/ 關閉（value=0）
  - 預設值：`old('status', $grade?->status->value ?? 1)`
- 送出按鈕與取消連結（`route('grades.index')`）

### Step 13: 側邊欄導覽

**修改** `resources/views/layouts/admin.blade.php`

```blade
<x-permission name="Grade.index">
    <a href="{{ route('grades.index') }}"
       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
              {{ request()->routeIs('grades.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
        版本管理
    </a>
</x-permission>
```

### Step 14: 測試

**新建** `tests/Feature/GradeCrudTest.php`

測試情境：
- Admin 可存取 `/grades`（200）
- Admin 可新增版本，資料庫確認建立
- Admin 可編輯版本（code / name / price / status）
- Admin 存取 `GET /grades/{grade}/edit` → 200，且表單預填值正確（code / name / price / status）
- 更新時允許 code / name 與自身相同（unique ignore）
- Admin 無法新增重複 code → 驗證錯誤
- Admin 無法新增重複 name → 驗證錯誤
- code 含特殊字元（如 `!@#`）→ regex 驗證錯誤
- name 含特殊字元 → regex 驗證錯誤
- price 為 1 → 驗證錯誤（min:2）
- price 為負數 → 驗證錯誤
- status 非 0 或 1 → 驗證錯誤（Enum 驗證）
- Admin 可從 index 切換啟用中版本 → 狀態變為關閉，redirect with success
- Admin 可從 index 切換關閉中版本 → 狀態變為啟用，redirect with success
- Viewer 嘗試切換狀態 → redirect（無 update 權限）
- Viewer 存取 `/grades/create` → redirect（無 create 權限）
- DELETE /grades/{id} 路由不存在（確認無刪除邏輯）

---

## 檔案清單

### 新建（14 個）

| 檔案 | 用途 |
|------|------|
| `database/migrations/*_create_grades_table.php` | grades 表結構 |
| `app/Enums/GradeStatus.php` | 狀態 Enum |
| `app/Models/Grade.php` | Grade Model |
| `database/factories/GradeFactory.php` | 測試用假資料工廠 |
| `database/seeders/GradeSeeder.php` | 預設版本資料 |
| `app/Repositories/GradeRepository.php` | 資料存取層 |
| `app/Services/GradeService.php` | 業務邏輯層 |
| `app/Http/Requests/GradeRequest.php` | 表單驗證 |
| `app/Http/Controllers/GradeController.php` | CRU Controller |
| `resources/views/admin/grades/index.blade.php` | 列表頁 |
| `resources/views/admin/grades/create.blade.php` | 新增頁 |
| `resources/views/admin/grades/edit.blade.php` | 編輯頁 |
| `resources/views/admin/grades/_form.blade.php` | 共用表單 |
| `tests/Feature/GradeCrudTest.php` | 功能測試 |

### 修改（4 個）

| 檔案 | 變更 |
|------|------|
| `database/seeders/PermissionSeeder.php` | 新增 Grade 模組（index / create / update，無 delete） |
| `database/seeders/DatabaseSeeder.php` | 呼叫 GradeSeeder |
| `routes/web.php` | 新增 5 條版本路由（無 delete） |
| `resources/views/layouts/admin.blade.php` | 側邊欄新增版本管理連結 |

---

## 驗證方式

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=GradeSeeder
php artisan route:list --path=grades
php artisan test --compact --filter=GradeCrud
```
