# 商店管理系統實作計畫

## Context

版本（Grade）系統完成後，本計畫新增 **商店（Shop）管理**功能。商店由兩張資料表組成：`shops`（商店基本資料）與 `shops_admin`（商店管理員資料），兩者為一對一關係。

本功能**僅支援 R（列表）與 U（編輯）**，無新增（C）與刪除（D）。

新增特性：
- 列表支援 **分頁**（page size 50/100/150/200）與**多條件交集搜尋**
- 商店管理員的 email 以 `encrypted` cast 加密儲存，顯示時解密後做 `*` 遮蔽；唯一性由應用層驗證
- 商店管理員的 business_number 在介面上顯示時做 `*` 遮蔽
- 商家認證流程：前端呼叫後端 `/shops/{shop}/certify` → 後端呼叫政府 API → 回傳 company_name → JS 填入表單 → 用戶點儲存後一併寫入 DB

---

## 架構設計

沿用 Controller / Service / Repository / Request / Blade Views 的完整分層架構（比照 GradeController 系列）。

### 資料欄位

#### `shops` 表

| 欄位 | 型別 | 說明 |
|------|------|------|
| id | PK bigint | |
| name | varchar(50) | 商店名稱 |
| email | varchar unique | 商店聯絡信箱 |
| grade_id | FK → grades | 版本 |
| status | tinyint | ShopStatus enum（啟用:1, 關閉:0, 過期:-1, 封存:-2） |
| created_at | datetime nullable | |
| updated_at | datetime nullable | |

#### `shops_admin` 表

| 欄位 | 型別 | 說明 |
|------|------|------|
| id | PK bigint | |
| shop_id | FK → shops | |
| name | varchar(20) | 管理員姓名 |
| email | text | 管理員信箱（`encrypted` cast；可編輯，顯示時解密後遮蔽；唯一性由應用層驗證） |
| password | varchar(255) | hashed cast |
| business_number | varchar nullable | 統一編號（唯讀，顯示時遮蔽；由認證流程填入） |
| company_name | varchar nullable | 公司名稱（唯讀；由認證流程填入） |
| created_at | datetime nullable | |
| updated_at | datetime nullable | |

### ShopStatus Enum

```php
namespace App\Enums;

enum ShopStatus: int
{
    case Active   = 1;   // 啟用
    case Closed   = 0;   // 關閉
    case Expired  = -1;  // 過期
    case Archived = -2;  // 封存
}
```

---

## 實作步驟

### Step 1: Migrations（2 個）

**新建** `database/migrations/YYYY_MM_DD_HHMMSS_create_shops_table.php`

```php
Schema::create('shops', function (Blueprint $table) {
    $table->id();
    $table->string('name', 50);
    $table->string('email')->unique();
    $table->foreignId('grade_id')->constrained('grades');
    $table->tinyInteger('status')->default(1);
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
```

**新建** `database/migrations/YYYY_MM_DD_HHMMSS_create_shops_admin_table.php`

```php
Schema::create('shops_admin', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
    $table->string('name', 20);
    $table->text('email');       // encrypted cast，DB 層不加 unique（加密值不可比對）
    $table->string('password');
    $table->string('business_number')->nullable()->index();
    $table->string('company_name')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
```

### Step 2: ShopStatus Enum

**新建** `app/Enums/ShopStatus.php`

```php
namespace App\Enums;

enum ShopStatus: int
{
    case Active   = 1;   // 啟用
    case Closed   = 0;   // 關閉
    case Expired  = -1;  // 過期
    case Archived = -2;  // 封存
}
```

### Step 3: Models

**新建** `app/Models/Shop.php`

```php
namespace App\Models;

use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'grade_id', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
        ];
    }

    public function grade(): BelongsTo   // belongsTo(Grade::class)
    public function admin(): HasOne      // hasOne(ShopAdmin::class)
}
```

**新建** `app/Models/ShopAdmin.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAdmin extends Model
{
    use HasFactory;

    protected $table = 'shops_admin';

    protected $fillable = ['shop_id', 'name', 'email', 'password', 'business_number', 'company_name'];

    protected function casts(): array
    {
        return [
            'email'    => 'encrypted',
            'password' => 'hashed',
        ];
    }

    public function shop(): BelongsTo   // belongsTo(Shop::class)
}
```

### Step 4: Helpers — 遮蔽函式

**新建** `app/Helpers/MaskHelpers.php`

奇數索引位置（index 1, 3, 5…）替換為 `*`：

```php
function maskEmail(string $email): string
// abc@gmail.com  → a*c@gmail.com
// admin@test.com → a*m*n@test.com

function maskString(string $value): string
// 12345678 → 1*3*5*7*
```

**修改** `composer.json`，在 `autoload` 加入：

```json
"files": [
    "app/Helpers/MaskHelpers.php"
]
```

### Step 5: Factories

**新建** `database/factories/ShopFactory.php`

```php
public function definition(): array
{
    return [
        'name'     => fake()->company(),
        'email'    => fake()->unique()->safeEmail(),
        'grade_id' => Grade::factory(),   // or existing grade ID
        'status'   => ShopStatus::Active,
    ];
}
```

**新建** `database/factories/ShopAdminFactory.php`

```php
public function definition(): array
{
    return [
        'name'            => fake()->name(),
        'email'           => fake()->unique()->safeEmail(),
        'password'        => 'password',
        'business_number' => null,
        'company_name'    => null,
    ];
}
```

### Step 5b: ShopSeeder

**新建** `database/seeders/ShopSeeder.php`

使用 `updateOrCreate` 保持冪等性，以 `email` 為唯一鍵。須在 `GradeSeeder` 之後執行（依賴 `grade_id`）。

每筆 shop 連帶建立對應的 shops_admin 記錄（同樣以 `shop_id` 為唯一鍵 upsert）。

#### 預設資料

| name | email | grade | status |
|------|-------|-------|--------|
| 正品旗艦店 | flagship@example.com | grade_s | 啟用 |
| 精選商行 | select@example.com | grade_a | 啟用 |
| 優質商店 | quality@example.com | grade_b | 關閉 |
| 過期範例店 | expired@example.com | grade_c | 過期 |

#### 對應 shops_admin 預設資料

| shop | name | email | business_number | company_name |
|------|------|-------|-----------------|--------------|
| 正品旗艦店 | 王小明 | admin@flagship.com | 12345678 | 正品有限公司 |
| 精選商行 | 李小華 | admin@select.com | null | null |
| 優質商店 | 陳大同 | admin@quality.com | null | null |
| 過期範例店 | 林小芳 | admin@expired.com | null | null |

密碼統一使用 `'password'`（`hashed` cast 自動處理雜湊）。

```php
public function run(): void
{
    $shops = [
        [
            'shop' => [
                'name'   => '正品旗艦店',
                'email'  => 'flagship@example.com',
                'grade_code' => 'grade_s',
                'status' => ShopStatus::Active->value,
            ],
            'admin' => [
                'name'            => '王小明',
                'email'           => 'admin@flagship.com',
                'password'        => 'password',
                'business_number' => '12345678',
                'company_name'    => '正品有限公司',
            ],
        ],
        [
            'shop' => [
                'name'       => '精選商行',
                'email'      => 'select@example.com',
                'grade_code' => 'grade_a',
                'status'     => ShopStatus::Active->value,
            ],
            'admin' => [
                'name'     => '李小華',
                'email'    => 'admin@select.com',
                'password' => 'password',
            ],
        ],
        [
            'shop' => [
                'name'       => '優質商店',
                'email'      => 'quality@example.com',
                'grade_code' => 'grade_b',
                'status'     => ShopStatus::Closed->value,
            ],
            'admin' => [
                'name'     => '陳大同',
                'email'    => 'admin@quality.com',
                'password' => 'password',
            ],
        ],
        [
            'shop' => [
                'name'       => '過期範例店',
                'email'      => 'expired@example.com',
                'grade_code' => 'grade_c',
                'status'     => ShopStatus::Expired->value,
            ],
            'admin' => [
                'name'     => '林小芳',
                'email'    => 'admin@expired.com',
                'password' => 'password',
            ],
        ],
    ];

    foreach ($shops as $data) {
        $grade = Grade::where('code', $data['shop']['grade_code'])->firstOrFail();

        $shop = Shop::updateOrCreate(
            ['email' => $data['shop']['email']],
            [
                'name'     => $data['shop']['name'],
                'grade_id' => $grade->id,
                'status'   => $data['shop']['status'],
            ],
        );

        ShopAdmin::updateOrCreate(
            ['shop_id' => $shop->id],
            array_merge(
                ['name' => $data['admin']['name'], 'email' => $data['admin']['email'], 'password' => $data['admin']['password']],
                ['business_number' => $data['admin']['business_number'] ?? null],
                ['company_name'    => $data['admin']['company_name'] ?? null],
            ),
        );
    }
}
```

**修改** `database/seeders/DatabaseSeeder.php`，在 `GradeSeeder` 之後呼叫：

```php
$this->call(GradeSeeder::class);
$this->call(ShopSeeder::class);
```

### Step 6: PermissionSeeder — 新增 Shop 模組

**修改** `database/seeders/PermissionSeeder.php`，在 `$modules` 新增（**無 create、無 delete**）：

```php
'Shop' => [
    'label' => '商店',
    'actions' => [
        'index'  => '列表',
        'update' => '編輯',
    ],
],
```

### Step 7: ShopRepository

**新建** `app/Repositories/ShopRepository.php`

```php
/**
 * @param array{keyword?: string, grade_id?: string, business_number?: string, certification?: string} $filters
 * certification: '' = 全部, '1' = 已認證（IS NOT NULL）, '0' = 未認證（IS NULL）
 */
public function paginate(int $perPage, array $filters): LengthAwarePaginator
// Shop::query()->with(['admin', 'grade'])
//   ->when($filters['keyword'])              → where('name', 'like', "%{v}%")
//   ->when($filters['grade_id'])             → where('grade_id', $v)
//   ->when($filters['business_number'])      → whereHas('admin', fn → where('business_number', $v))
//   ->when($filters['certification'] === '1')→ whereHas('admin', fn → whereNotNull('business_number'))
//   ->when($filters['certification'] === '0')→ whereHas('admin', fn → whereNull('business_number'))
//   ->orderBy('id')->paginate($perPage)
// 使用 id ASC 確保分頁結果穩定（需求未指定排序）

public function update(Shop $shop, array $data): void
// $shop->update($data)

public function updateAdmin(ShopAdmin $admin, array $data): void
// $admin->update($data)
```

### Step 8: ShopService

**新建** `app/Services/ShopService.php`

注入 `ShopRepository`：

```php
/**
 * @return array{shops: LengthAwarePaginator, filters: array, perPage: int}
 */
public function getIndexData(Request $request): array
// perPage = in_array($request->per_page, [50,100,150,200]) ? $request->per_page : 50
// filters = $request->only(['keyword', 'grade_id', 'business_number', 'certification'])
// shops   = $repo->paginate($perPage, $filters)

/**
 * @return array{shop: Shop, grades: Collection, statuses: ShopStatus[]}
 */
public function getEditData(Shop $shop): array
// $shop->load('admin', 'grade')
// grades  = Grade::all()
// statuses = ShopStatus::cases()

public function updateShop(Shop $shop, array $shopData, array $adminData): void
// 1. 應用層 email 唯一性驗證（解密比對，排除自身）
//    ShopAdmin::all()->reject(fn($a) => $a->id === $shop->admin->id)
//             ->first(fn($a) => $a->email === $adminData['email'])
//    → 衝突時拋出 ValidationException(['admin.email' => '此 email 已被使用'])
// 2. $repo->update($shop, $shopData)
// 3. $repo->updateAdmin($shop->admin, $adminData)

/**
 * 呼叫政府 API 驗證統一編號
 * $businessNumber 呼叫前已確保為純數字字串（controller validate 把關）
 * @return array{success: bool, company_name?: string}
 */
public function verifyCertification(string $businessNumber): array
// try {
//     $response = Http::timeout(10)->get(
//         'http://data.gcis.nat.gov.tw/od/data/api/5F64D864-61CB-4D0D-8AD9-492047CC1EA6',
//         [
//             '$format' => 'json',
//             '$filter' => 'Business_Accounting_NO eq ' . $businessNumber,
//             '$skip'   => 0,
//             '$top'    => 1,
//         ]
//     );
//     $data = $response->json();
//     if (!empty($data)) {
//         return ['success' => true, 'company_name' => $data[0]['Company_Name']];
//     }
//     return ['success' => false];
// } catch (\Exception $e) {
//     return ['success' => false];
// }
```

### Step 9: ShopUpdateRequest

**新建** `app/Http/Requests/ShopUpdateRequest.php`

表單使用 nested name（`admin[name]`、`admin[email]`...），對應驗證 key 為點號格式：

```php
'name'                    => ['required', 'string', 'max:50'],
'email'                   => ['required', 'email',
                              Rule::unique('shops', 'email')->ignore($this->route('shop')->id)],
'grade_id'                => ['required', 'integer', Rule::exists('grades', 'id')],
'status'                  => ['required', new Enum(ShopStatus::class)],
'admin.name'              => ['required', 'string', 'max:20'],
'admin.email'             => ['required', 'email'],
// email 唯一性無法用 Rule::unique（DB 存加密值）
// 改由 ShopService::updateShop() 在寫入前解密比對：
// ShopAdmin::all()->reject(fn($a) => $a->id === $shop->admin->id)
//          ->first(fn($a) => $a->email === $adminData['email'])
// → 若有衝突則拋出 ValidationException
'admin.business_number'   => ['nullable', 'string', 'regex:/^\d{8}$/'],
'admin.company_name'      => ['nullable', 'string'],
```

### Step 10: ShopController

**新建** `app/Http/Controllers/ShopController.php`

CRU 方法（**無 create / store / destroy**）+ certify：

```php
#[RequiresPermission('Shop.index')]
public function index(Request $request)
// return view('admin.shops.index', $this->shopService->getIndexData($request))

#[RequiresPermission('Shop.update')]
public function edit(Shop $shop)
// return view('admin.shops.edit', $this->shopService->getEditData($shop))

#[RequiresPermission('Shop.update')]
public function update(ShopUpdateRequest $request, Shop $shop)
// shopData  = $request->only(['name', 'email', 'grade_id', 'status'])
// adminData = $request->input('admin')  // ['name', 'email', 'business_number', 'company_name']
// $this->shopService->updateShop($shop, $shopData, $adminData)
// redirect()->route('shops.index')->with('success', '商店已更新')

#[RequiresPermission('Shop.update')]
public function certify(Request $request, Shop $shop)
// $request->validate(['business_number' => 'required|string|regex:/^\d{8}$/'])
// $result = $this->shopService->verifyCertification($request->business_number)
// return response()->json($result)
```

### Step 11: Routes

**修改** `routes/web.php`（在 `permission` middleware group 內，**無 create / delete**）：

```php
// 商店管理
Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
Route::get('/shops/{shop}/edit', [ShopController::class, 'edit'])->name('shops.edit');
Route::put('/shops/{shop}', [ShopController::class, 'update'])->name('shops.update');
Route::post('/shops/{shop}/certify', [ShopController::class, 'certify'])->name('shops.certify');
```

### Step 12: Blade Views

#### `resources/views/admin/shops/index.blade.php`

**搜尋區塊**（GET form，保留分頁參數）：

| 搜尋欄位 | 控件 | 對應欄位 | 比對方式 |
|---------|------|---------|---------|
| 關鍵字 | text input | shops.name | LIKE %v% |
| 版本 | `<select>`（空白選項 + 所有 Grade） | shops.grade_id | `=`（精準） |
| 統一編號 | text input | shops_admin.business_number | `=`（精準） |
| 認證狀態 | `<select>`（全部 / 已認證 / 未認證） | shops_admin.business_number | IS NOT NULL / IS NULL |

版本 select 選項由 `$grades`（`getIndexData` 回傳）渲染，value 為 `grade_id`。  
認證狀態 select value 設計：`''`（全部）、`'1'`（已認證）、`'0'`（未認證）。

**Page Size 選擇**：下拉選單（50 / 100 / 150 / 200），onChange 提交 form。

**資料表格欄位**：商店名稱 / 版本 / 狀態 / 認證狀態 / 操作

**認證狀態欄**：
- 已認證 → 可點擊 badge（`cursor-pointer`），點擊後開啟詳情 Modal
- 未認證 → 不可點擊灰色 badge

**認證詳情 Modal**（純 JS，點擊 badge 帶入資料後顯示）：
```
統一編號：{business_number}
公司名稱：{company_name}
```

**操作欄**：`<x-permission name="Shop.update">` 包裹「編輯」連結。

**分頁**：
```blade
{{ $shops->appends(request()->query())->links() }}
```

---

#### `resources/views/admin/shops/edit.blade.php`

單一 `<form method="POST" action="{{ route('shops.update', $shop) }}">` + `@method('PUT')`，分兩個 `<section>` 呈現：

**區塊一 — 商店基本資料**

| 欄位 | form name | 控件 | 備註 |
|------|-----------|------|------|
| name | `name` | text input | 可編輯 |
| email | `email` | text input | 可編輯，unique（排除自身） |
| grade_id | `grade_id` | `<select>` | 列出所有 Grade |
| status | `status` | `<select>` | ShopStatus cases，中文 label |

**區塊二 — 商店管理員基本資料**

採用 nested name（`admin[name]`、`admin[email]`...），後端以 `$request->input('admin')` 取得：

| 欄位 | form name | 控件 | 備註 |
|------|-----------|------|------|
| name | `admin[name]` | text input | 可編輯 |
| email | `admin[email]` | 遮蔽顯示 span + hidden input + 修改按鈕 | 顯示遮蔽值；點修改 → JS 切換 hidden→text input |
| business_number | `admin[business_number]`（hidden）| readonly text input（遮蔽）+ hidden input | hidden input 攜帶值供送出；JS 認證成功後更新 |
| company_name | `admin[company_name]`（hidden）| readonly text input | hidden input 攜帶值供送出；JS 認證成功後更新 |

**認證狀況 badge**（business_number 欄位旁）：
- 已認證（business_number not null）→ 灰色不可點標籤
- 進行認證（business_number null）→ 藍色可點按鈕，點後開認證 Modal

**認證 Modal**（進行認證流程）：

**按鈕初始狀態**（每次開啟 Modal 時重置）：
- 右下角按鈕文字：「認證」
- business_number input 清空
- 結果訊息區塊隱藏

**流程**：

1. Input：填入 business_number（前端限制只允許數字）
2. 點「認證」→ `fetch POST /shops/{shop}/certify` with CSRF token，body `{ business_number }`
3. **成功**：
   - 顯示 `company_name`，提示「請儲存商店資料以完成認證流程」
   - JS 更新：`admin[business_number]` hidden input 的 value、對應 readonly display input 的遮蔽值、`admin[company_name]` hidden input 的 value、readonly display input 的值
   - 右下角按鈕文字改為「完成」，點擊後關閉 Modal
4. **失敗**（含 API 連線失敗）：顯示「認證失敗，請確認統一編號是否正確」，按鈕維持「認證」可重試

### Step 13: 側邊欄

**修改** `resources/views/layouts/admin.blade.php`：

```blade
<x-permission name="Shop.index">
    <a href="{{ route('shops.index') }}"
       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
              {{ request()->routeIs('shops.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
        商店管理
    </a>
</x-permission>
```

### Step 14: 測試

**新建** `tests/Feature/ShopRuTest.php`

測試情境：
- Admin 可存取 `/shops`（200）
- Viewer 可存取 `/shops`（200，有 `Shop.index` 權限）
- Admin 可存取 `/shops/{shop}/edit`（200），表單包含商店 name、email、grade_id、status、admin name、遮蔽 email
- Admin 可更新商店（name / email / grade_id / status / admin[name] / admin[email]）→ redirect with success，資料庫確認更新
- 更新 email 允許與自身相同（unique ignore self for shops.email）
- 更新 admin[email] 允許與自身相同（unique ignore self for shops_admin.email）
- shops.email 與他筆重複 → 驗證錯誤（`email`）
- admin[email] 與他筆重複 → 驗證錯誤（`admin.email`）
- status 非合法 ShopStatus 值 → 驗證錯誤（`status`）
- grade_id 不存在 → 驗證錯誤（`grade_id`）
- 認證後儲存：admin[business_number] + admin[company_name] 一併寫入 DB
- certify 缺少 business_number → 422 驗證錯誤
- certify business_number 含非數字字元或非 8 位 → 422 驗證錯誤
- certify：Mock `Http` facade，回傳合法資料 → JSON `{ success: true, company_name: "..." }`
- certify：Mock `Http` facade，回傳空陣列 → JSON `{ success: false }`
- certify：Mock `Http` facade 拋出 ConnectionException → JSON `{ success: false }`
- Viewer 存取 `/shops/{shop}/edit` → redirect（無 `Shop.update` 權限）
- `POST /shops`（store）不存在 → 405
- `DELETE /shops/{shop}` 不存在 → 405

---

## 檔案清單

### 新建（16 個）

| 檔案 | 用途 |
|------|------|
| `database/migrations/*_create_shops_table.php` | shops 表結構 |
| `database/migrations/*_create_shops_admin_table.php` | shops_admin 表結構 |
| `app/Enums/ShopStatus.php` | 狀態 Enum（4 值） |
| `app/Models/Shop.php` | Shop Model |
| `app/Models/ShopAdmin.php` | ShopAdmin Model |
| `app/Helpers/MaskHelpers.php` | maskEmail / maskString 遮蔽函式 |
| `database/factories/ShopFactory.php` | 測試假資料 |
| `database/factories/ShopAdminFactory.php` | 測試假資料 |
| `database/seeders/ShopSeeder.php` | 預設商店與管理員資料（4 筆） |
| `app/Repositories/ShopRepository.php` | 資料存取層（含分頁 + 篩選） |
| `app/Services/ShopService.php` | 業務邏輯層（含政府 API 認證） |
| `app/Http/Requests/ShopUpdateRequest.php` | 表單驗證 |
| `app/Http/Controllers/ShopController.php` | RU + certify Controller |
| `resources/views/admin/shops/index.blade.php` | 列表頁（搜尋 + 分頁 + 認證 Modal） |
| `resources/views/admin/shops/edit.blade.php` | 編輯頁（雙區塊 + 認證 Modal） |
| `tests/Feature/ShopRuTest.php` | 功能測試 |

### 修改（5 個）

| 檔案 | 變更 |
|------|------|
| `database/seeders/PermissionSeeder.php` | 新增 Shop 模組（index / update，無 create / delete） |
| `database/seeders/DatabaseSeeder.php` | 在 GradeSeeder 後呼叫 ShopSeeder |
| `routes/web.php` | 新增 4 條商店路由（無 create / delete） |
| `resources/views/layouts/admin.blade.php` | 側邊欄新增商店管理連結 |
| `composer.json` | `autoload.files` 加入 `app/Helpers/MaskHelpers.php` |

---

## 驗證方式

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=GradeSeeder
php artisan db:seed --class=ShopSeeder
composer dump-autoload
php artisan route:list --path=shops
php artisan test --compact --filter=ShopRu
```

手動驗證：
1. 以 Admin 登入，進入商店管理列表
2. 確認分頁切換（50/100/150/200）正常
3. 各搜尋條件（單一 / 組合）結果取交集
4. 點「已認證」badge 顯示 Modal（統一編號 + 公司名稱）
5. 進入編輯頁，確認 email 遮蔽、business_number 遮蔽
6. 觸發「進行認證」Modal，填入合法統一編號 → 認證成功並顯示公司名稱
7. 儲存 → shops 與 shops_admin 資料正確更新
8. 以 Viewer 登入，確認無法進入編輯頁
