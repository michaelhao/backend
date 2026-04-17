# 版本權重系統實作計畫

## Context

版本（Grade）目前缺乏排序依據，無法表達等級高低關係。本計畫新增 `weight` 欄位，數值越高代表等級越高，並在 index 列表加入權重欄、create/edit 表單加入即時重複驗證與位置預覽。

---

## 架構設計

### 資料欄位

| 欄位 | 型別 | 說明 |
|------|------|------|
| weight | int | 權重值，數值越高等級越高（例：S=100, A=85, E=25） |

### 預設版本權重

| name | weight |
|------|--------|
| 版本S | 100 |
| 版本A | 85 |
| 版本B | 70 |
| 版本C | 55 |
| 版本D | 40 |
| 版本E | 25 |

---

## 實作步驟

### Step 1: Migration

**新建** `database/migrations/*_add_weight_to_grades_table.php`

```php
public function up(): void
{
    Schema::table('grades', function (Blueprint $table) {
        $table->integer('weight')->default(0)->after('price');
    });
}

public function down(): void
{
    Schema::table('grades', function (Blueprint $table) {
        $table->dropColumn('weight');
    });
}
```

### Step 2: Grade Model

**修改** `app/Models/Grade.php`

`$fillable` 新增 `'weight'`：

```php
protected $fillable = ['code', 'name', 'price', 'weight', 'status'];
```

### Step 3: GradeRepository

**修改** `app/Repositories/GradeRepository.php`

- `getAll()`：改為 `Grade::orderByDesc('weight')->get()`（index 列表、表單顯示區皆依此排序）
- 新增 `findByWeight()`：

```php
public function findByWeight(int $weight, ?int $excludeId): ?Grade
{
    return Grade::where('weight', $weight)
        ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
        ->first();
}
```

### Step 4: GradeService

**修改** `app/Services/GradeService.php`

- 新增 `getAllGrades(): Collection`（供表單顯示區與 `checkWeight` 共用，避免耦合 `getCreateData()`）：
  ```php
  public function getAllGrades(): Collection
  {
      return $this->gradeRepository->getAll();
  }
  ```
- `getCreateData()`：改回傳 `['grades' => $this->getAllGrades()]`
- `getEditData(Grade $grade)`：改回傳 `['grade' => $grade, 'grades' => $this->getAllGrades()]`
- 新增 `findByWeight()`：
  ```php
  public function findByWeight(int $weight, ?int $excludeId): ?Grade
  {
      return $this->gradeRepository->findByWeight($weight, $excludeId);
  }
  ```

### Step 5: GradeRequest

**修改** `app/Http/Requests/GradeRequest.php`

新增 `weight` 驗證規則：

```php
'weight' => ['required', 'integer', 'min:1', Rule::unique('grades', 'weight')->ignore($gradeId)],
```

### Step 6: GradeController

**修改** `app/Http/Controllers/GradeController.php`

新增 `checkWeight()` 方法，綁定 `Grade.update` 權限（與 edit/update 一致，僅有編輯權限者可查詢）：

```php
#[RequiresPermission('Grade.update')]
public function checkWeight(Request $request): JsonResponse
{
    $weight    = (int) $request->query('weight');
    $excludeId = $request->query('exclude_id') ? (int) $request->query('exclude_id') : null;

    $conflict = $this->gradeService->findByWeight($weight, $excludeId);
    $grades   = $this->gradeService->getAllGrades();

    return response()->json([
        'duplicate'         => $conflict !== null,
        'conflicting_grade' => $conflict
            ? ['id' => $conflict->id, 'name' => $conflict->name, 'weight' => $conflict->weight]
            : null,
        'grades' => $grades->map(fn($g) => ['id' => $g->id, 'name' => $g->name, 'weight' => $g->weight]),
    ]);
}
```

### Step 7: Routes

**修改** `routes/web.php`

在 grades 路由群組加入：

```php
Route::get('/grades/check-weight', [GradeController::class, 'checkWeight'])->name('grades.check-weight');
```

### Step 8: index.blade.php

**修改** `resources/views/admin/grades/index.blade.php`

- `<thead>` 在「名稱」後新增 `<th class="px-6 py-3">權重</th>`
- `<tbody>` 對應位置新增 `<td class="px-6 py-4 text-gray-500">{{ $grade->weight }}</td>`

### Step 9: _form.blade.php

**修改** `resources/views/admin/grades/_form.blade.php`

在 price 欄位後新增版本權重區塊：

```blade
{{-- 版本權重顯示區 --}}
<div>
    <p class="text-xs font-semibold text-gray-500 mb-2">grades weight</p>
    <div id="weight-list" class="text-sm text-gray-700 space-y-1 border rounded-lg p-3 bg-gray-50">
        @foreach ($grades as $g)
            <div class="flex justify-between weight-row" data-id="{{ $g->id }}">
                <span>{{ $g->name }}</span>
                <span>{{ $g->weight }}</span>
            </div>
        @endforeach
    </div>
</div>

{{-- weight 輸入 --}}
<div>
    <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">版本權重</label>
    <input type="number" name="weight" id="weight"
           value="{{ old('weight', $grade->weight ?? '') }}"
           data-exclude-id="{{ $grade->id ?? '' }}"
           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
    <div id="weight-error" class="mt-1 text-sm text-red-600 hidden"></div>
    @error('weight')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
```

### Step 10: create.blade.php / edit.blade.php

**修改** 兩個頁面的 `@push('scripts')` 區塊加入：

```blade
@vite('resources/js/grades/form.js')
```

### Step 11: form.js

**新建** `resources/js/grades/form.js`

邏輯說明：

- `DOMContentLoaded` 後綁定 `#weight` 的 `change` 事件
- `onchange` 且輸入值非空時 → `axios.get('/grades/check-weight', { params: { weight, exclude_id } })`

**重複（duplicate: true）**：
- `#weight-error` 顯示「請確認版本權重」
- 在 `#weight-list` 找到衝突 grade 的 `.weight-row[data-id]`，加上 `text-red-600 font-semibold` 標示
- 移除動態插入的預覽列（`.weight-preview`）

**不重複（duplicate: false）**：
- 隱藏 `#weight-error`，移除所有衝突標示
- 移除舊的 `.weight-preview`
- 根據回傳 grades 陣列找出插入位置（weight 降序，找到第一個比輸入值小的位置之前），插入預覽列：
  ```html
  <div class="flex justify-between weight-preview text-blue-600 font-medium">
      <span>（設定位置）</span><span>{weight}</span>
  </div>
  ```

**輸入值為空時**：移除預覽列與錯誤訊息，不送出請求。

```js
import axios from 'axios';

document.addEventListener('DOMContentLoaded', () => {
    const input     = document.getElementById('weight');
    const errorEl   = document.getElementById('weight-error');
    const listEl    = document.getElementById('weight-list');
    if (!input) return;

    input.addEventListener('change', async function () {
        const weight    = this.value.trim();
        const excludeId = this.dataset.excludeId || null;

        // 清除舊狀態
        listEl.querySelectorAll('.weight-row').forEach(r => r.classList.remove('text-red-600', 'font-semibold'));
        listEl.querySelectorAll('.weight-preview').forEach(r => r.remove());
        errorEl.classList.add('hidden');
        errorEl.textContent = '';

        if (!weight) return;

        const { data } = await axios.get('/grades/check-weight', {
            params: { weight, exclude_id: excludeId || undefined },
        });

        if (data.duplicate) {
            errorEl.textContent = '請確認版本權重';
            errorEl.classList.remove('hidden');
            const conflictRow = listEl.querySelector(`.weight-row[data-id="${data.conflicting_grade.id}"]`);
            if (conflictRow) conflictRow.classList.add('text-red-600', 'font-semibold');
        } else {
            const preview = document.createElement('div');
            preview.className = 'flex justify-between weight-preview text-blue-600 font-medium';
            preview.innerHTML = `<span>（設定位置）</span><span>${weight}</span>`;

            const rows  = [...listEl.querySelectorAll('.weight-row')];
            const after = rows.find(r => {
                const siblingGrade = data.grades.find(g => g.id == r.dataset.id);
                return siblingGrade && siblingGrade.weight < parseInt(weight);
            });

            after ? listEl.insertBefore(preview, after) : listEl.appendChild(preview);
        }
    });
});
```

### Step 12: GradeSeeder

**修改** `database/seeders/GradeSeeder.php`

各項目加入 weight 值：

```php
$grades = [
    ['name' => '版本S', 'code' => 'grade_s', 'price' => 10000, 'weight' => 100],
    ['name' => '版本A', 'code' => 'grade_a', 'price' => 9000,  'weight' => 85],
    ['name' => '版本B', 'code' => 'grade_b', 'price' => 8000,  'weight' => 70],
    ['name' => '版本C', 'code' => 'grade_c', 'price' => 7000,  'weight' => 55],
    ['name' => '版本D', 'code' => 'grade_d', 'price' => 6000,  'weight' => 40],
    ['name' => '版本E', 'code' => 'grade_e', 'price' => 5000,  'weight' => 25],
];
```

`updateOrCreate` 更新陣列加入 `'weight' => $grade['weight']`。

### Step 13: GradeFactory

**修改** `database/factories/GradeFactory.php`

```php
'weight' => fake()->unique()->numberBetween(1, 999),
```

---

## 檔案清單

### 新建（2 個）

| 檔案 | 用途 |
|------|------|
| `database/migrations/*_add_weight_to_grades_table.php` | grades 表新增 weight 欄位 |
| `resources/js/grades/form.js` | 版本權重即時驗證與位置預覽邏輯 |

### 修改（12 個）

| 檔案 | 變更 |
|------|------|
| `app/Models/Grade.php` | `$fillable` 加入 weight |
| `app/Repositories/GradeRepository.php` | getAll 改 orderByDesc weight；新增 findByWeight |
| `app/Services/GradeService.php` | 新增 getAllGrades；getCreateData / getEditData 改呼叫 getAllGrades；新增 findByWeight |
| `app/Http/Requests/GradeRequest.php` | 新增 weight 驗證規則（required、integer、min:1、unique） |
| `app/Http/Controllers/GradeController.php` | 新增 checkWeight（Grade.update 權限） |
| `routes/web.php` | 新增 GET /grades/check-weight 路由 |
| `resources/views/admin/grades/index.blade.php` | 新增 weight 欄位 |
| `resources/views/admin/grades/_form.blade.php` | 新增權重顯示區與 weight 輸入欄位 |
| `resources/views/admin/grades/create.blade.php` | @push scripts 加入 form.js |
| `resources/views/admin/grades/edit.blade.php` | @push scripts 加入 form.js |
| `database/seeders/GradeSeeder.php` | 各版本加入 weight 值 |
| `database/factories/GradeFactory.php` | 加入 weight 欄位 |

---

## 驗證方式

```bash
php artisan migrate
php artisan db:seed --class=GradeSeeder
php artisan route:list --path=grades
php artisan test --compact --filter=GradeCrud
```

手動驗證：

1. index 頁面顯示 weight 欄，資料依 weight 降序排列
2. create/edit 頁面顯示版本權重列表及 weight 輸入框
3. 輸入已存在的 weight → 顯示「請確認版本權重」並標紅衝突版本
4. 輸入不重複的 weight → 顯示預覽列於正確排序位置
5. 送出表單，weight 空值或重複 → server-side validation 擋住
