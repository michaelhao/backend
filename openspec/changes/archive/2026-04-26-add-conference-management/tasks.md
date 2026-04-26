## 1. 資料層

- [x] 1.1 建立 migration `database/migrations/2026_04_24_000001_create_conferences_table.php`：
    - id 使用 `$table->increments('id')`（Laravel 慣例；等同 unsigned int auto-increment primary。**不可**用 `unsignedInteger('id')->autoIncrement()->primary()`，SQLite 會 duplicate primary key）
    - `name` 用 `string('name', 100)`；`status` 用 `tinyInteger('status')->default(1)`
    - 四個時間欄位用 `dateTime(...)`
    - 依 CLAUDE.md migrate rules **不可用 `$table->timestamps()`**，改寫：`$table->dateTime('created_at')->nullable();` 與 `$table->dateTime('updated_at')->nullable();`
    - 加上 `$table->index('status')` 與 `$table->index('started_at')`
- [x] 1.2 Docker 內執行 `docker exec wsl-backend php artisan migrate` 確認 schema 產生
- [x] 1.3 建立 `app/Enums/ConferenceStatus.php`（int enum：Active=1、Inactive=0；**不含 Deleted**）
- [x] 1.4 建立 `app/Models/Conference.php`：`$fillable = ['name','status','started_at','ended_at','register_started_at','register_ended_at']`；`casts()` 把 `status` cast 成 `ConferenceStatus::class`、四個時間欄位 cast 成 `'datetime'`
- [x] 1.5 建立 `database/factories/ConferenceFactory.php`：用固定相對時間模板避免 flaky（建議：`register_started_at = now()->subDays(7)`、`register_ended_at = now()->subDays(1)`、`started_at = now()->addDays(7)`、`ended_at = now()->addDays(7)->addHours(2)`），`status` 隨機在 Active / Inactive

## 2. Repository / Service

- [x] 2.1 建立 `app/Repositories/ConferenceRepository.php`，提供 `paginate(int $perPage, array $filters)`（支援 `keyword` LIKE 搜尋 `name`、`status` 精確比對）、`create(array)`、`update(Conference, array)`
- [x] 2.2 建立 `app/Services/ConferenceService.php`，提供 `getIndexData(Request)`、`getCreateData()`、`getEditData(Conference)`、`createConference(array)`、`updateConference(Conference, array)`；`create` / `update` 以 `DB::transaction` 包住維持專案慣例
- [x] 2.3 `getIndexData` 的 `per_page` 處理比照 [AddonService L32](app/Services/AddonService.php#L32)：`in_array((int) $request->per_page, [50, 100, 150, 200]) ? (int) $request->per_page : 50`

## 3. HTTP 層

- [x] 3.1 建立 `app/Http/Requests/ConferenceRequest.php`：
    - `authorize()` 回傳 `true`（授權由 `permission` middleware 處理）
    - `name`: `['required', 'string', 'max:100']`
    - `status`: `['required', Rule::in([ConferenceStatus::Active->value, ConferenceStatus::Inactive->value])]`（對齊 [AddonRequest L25](app/Http/Requests/AddonRequest.php#L25)）
    - `started_at`: `['required', 'date']`
    - `ended_at`: `['required', 'date', 'after:started_at']`
    - `register_started_at`: `['required', 'date', 'before_or_equal:started_at']`
    - `register_ended_at`: `['required', 'date', 'after:register_started_at', 'before_or_equal:started_at']`
- [x] 3.2 建立 `app/Http/Controllers/ConferenceController.php`：`index` / `create` / `store` / `edit` / `update` 五個 action。`#[RequiresPermission]` 為 `TARGET_METHOD`（見 [RequiresPermission L13](app/Attributes/RequiresPermission.php#L13)），**必須放在每個 method 上**、不可放 class 上。對應：
    - `index` → `#[RequiresPermission('Conference.index')]`
    - `create`、`store` → `#[RequiresPermission('Conference.create')]`
    - `edit`、`update` → `#[RequiresPermission('Conference.update')]`

  edit/update 找不到 id 時 `redirect()->route('conferences.index')->with('error', '找不到該說明會')`。
- [x] 3.3 在 [routes/web.php](routes/web.php) 的 `permission` middleware 群組新增 5 條路由（index、create、store、edit、update），命名為 `conferences.*`；**不新增 DELETE 路由**

## 4. 權限註冊

- [x] 4.1 編輯 [database/seeders/PermissionSeeder.php](database/seeders/PermissionSeeder.php)，在 `$modules` 陣列新增：
    ```php
    'Conference' => [
        'label' => '說明會',
        'actions' => [
            'index'  => '列表',
            'create' => '新增',
            'update' => '編輯',
        ],
    ],
    ```
    **不加 `delete` action。**
- [x] 4.2 Docker 內執行 `docker exec wsl-backend php artisan db:seed --class=PermissionSeeder`，確認 `permissions` 表出現 3 筆 `Conference.*`，並且 Admin role 取得新權限、Viewer role 取得 `Conference.index`
- [x] 4.3 查詢 `Permission::where('name', 'Conference.delete')->exists()` 應為 `false`（在測試中斷言）

## 5. Views

- [x] 5.1 建立 `resources/views/admin/conferences/index.blade.php`：列表、關鍵字 / status 篩選、分頁、連結到 create / edit；**不顯示刪除按鈕**
- [x] 5.2 建立 `resources/views/admin/conferences/create.blade.php`：name、status select、四個 datetime input（共用 `_form.blade.php` partial）
- [x] 5.3 建立 `resources/views/admin/conferences/edit.blade.php`：同上，預填當前資料
- [x] 5.4 註冊 vite entry 與 flash auto-dismiss JS hook（沿用 sibling 模組慣例）：
    - 新增 `resources/js/conferences/index.js`，呼叫 `autoDismissFlashes()`
    - `vite.config.js` input 陣列加入該檔
    - `index.blade.php` 加 `@push('scripts') @vite('resources/js/conferences/index.js') @endpush`

## 6. 測試

- [x] 6.1 建立 `tests/Feature/Conference/ConferenceCrudTest.php`，涵蓋：
    - [x] 6.1.1 Happy path：授權使用者建立 / 看到列表 / 編輯 / 更新
    - [x] 6.1.2 權限測試：未授權使用者（Viewer role 僅有 `*.index`、或無 `Conference.*` 的角色）存取 create/store/edit/update 4 條路由時，用 `assertRedirect()` 斷言（CheckPermission 實際會 redirect 到 `default_route`，**不是** 403）
    - [x] 6.1.3 驗證時間順序錯誤情境（至少三種：`register_ended_at > started_at`、`ended_at ≤ started_at`、`register_started_at ≥ register_ended_at`）
    - [x] 6.1.4 邊界：`register_ended_at = started_at` 應成功
    - [x] 6.1.5 找不到 id 的 edit/update 導向 index 並帶 error flash
    - [x] 6.1.6 DELETE `/conferences/{id}` 回應 **405**（因 PUT `/conferences/{id}` 已存在，Laravel 回 405 Method Not Allowed；防止未來誤加 DELETE 路由）
    - [x] 6.1.7 斷言 `Permission::where('name', 'Conference.delete')->exists()` 為 `false`
    - [x] 6.1.8 必填缺漏斷言（`name` / `status` / 任一時間欄位缺）→ 422 或 redirect with errors，資料庫無新增
- [x] 6.2 Docker 內執行 `docker exec wsl-backend php artisan test --compact tests/Feature/Conference/ConferenceCrudTest.php` 通過（23 passed）

## 7. 收尾

- [x] 7.1 Docker 內執行 `docker exec wsl-backend vendor/bin/pint --dirty --format agent` 通過
- [x] 7.2 手動驗證 `/conferences`：新增 → 列表 → 編輯 → status 切 Inactive → 關鍵字 / status 篩選皆正常（使用者自行驗證）
- [x] 7.3 `openspec validate add-conference-management --strict` 通過
