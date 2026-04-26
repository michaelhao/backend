## Context

本專案後台既有多組 CRUD 模組（Addon、Grade、Role、User 等），皆遵守 Controller → Service → Repository 的分層，狀態以 int-backed enum 表達，軟刪則以 `status = Deleted` 代替 `DELETE` SQL。此次新增的「說明會管理」只有單表、沒有關聯、沒有檔案上傳，是既有模板的最小子集。

## Goals / Non-Goals

**Goals**
- 以最少程式碼新增 `conferences` 資料表與對應的 Model / Repository / Service / Controller / Request。
- 對齊專案既有慣例：`#[RequiresPermission]`、Blade view 位置、`DB::transaction` 包住寫入動作、`vendor/bin/pint` 通過。
- 以 FormRequest 集中驗證四個時間欄位之間的順序，避免 Service / Controller 出現業務邏輯分散。

**Non-Goals**
- 名額 / 報名相關欄位與流程（屬另一需求）。
- 任何刪除行為（含軟刪）——刻意保留歷史紀錄。
- 前台頁面、外部 API、寄信 / 通知、排程推進 status。
- 與 grade / shop / user 建立關聯。

## Decisions

### 1. 沿用 Addon 模板，而非 make:resource 或 Filament 等工具
**選擇**：手動建立 7 個檔案，複製 `AddonController` / `AddonService` / `AddonRepository` 結構。
**替代案**：
- Laravel resource controller：會帶入 `show` / `destroy`，不符合「無刪除」需求。
- Filament / Nova：專案未引入，不合 “Do not change the application's dependencies” 規則。

**理由**：專案既有慣例以手工組 CRUD 為主（Addon、Grade），維持一致性、審閱成本低。

### 2. Status 只有 Active / Inactive，無 Deleted
**選擇**：`ConferenceStatus` enum 僅兩個值。
**替代案**：比照 Addon 保留 Deleted = -1 以支援軟刪。

**理由**：本次功能不允許刪除，放 Deleted 反而會讓後續讀者誤以為有 destroy 路徑。要下架時 status = Inactive 即可。

### 3. 時間順序驗證放在 FormRequest
**選擇**：在 `ConferenceRequest::rules()` 中使用 `after` / `before_or_equal` 規則描述四個 datetime 的關係。
**替代案**：在 Service 中手動檢查並拋 `ValidationException`。

**理由**：Laravel 提供的 `after:field`、`before_or_equal:field` 足以表達 `register_started_at < register_ended_at ≤ started_at < ended_at`，集中在 FormRequest 避免業務規則外洩。

### 4. `id` 使用 `int unsigned` 而非 `bigint`
**選擇**：Migration 使用 `$table->unsignedInteger('id')->autoIncrement()->primary()`。
**替代案**：Laravel 預設 `$table->id()`（bigint）。

**理由**：使用者明確要求。說明會數量不會接近 int 上限（~42 億），int 即可。

### 5. 不加 soft delete 欄位 / `destroy` action
**選擇**：Controller 不提供 `destroy`，路由不註冊 DELETE，權限表不加 `Conference.delete`，`ConferenceStatus` 也不保留未用的 `Deleted = -1`。
**替代案**：保留軟刪機制與 `Deleted` enum 值以備未來使用。

**理由**：保留歷史紀錄是刻意的產品決策；留下未使用的 `Deleted` 值會讓 reader 誤以為有軟刪路徑，反而是負面訊號，因此刻意移除。未來若真要刪除，屬獨立變更，應走新的 OpenSpec change。

### 6. `name` 允許重複
**選擇**：`name` 欄位不加 unique constraint。
**替代案**：加 unique index。

**理由**：同一個主題的說明會可能跨期反覆舉辦（例如「春季招商說明會」每年都辦一次），強制唯一會阻擋合法使用情境。搜尋時以 `LIKE` 方式處理關鍵字即可。

### 7. 時間欄位不與 `now()` 比較
**選擇**：四個 datetime 的驗證只檢查彼此順序，不與當下時間比較。
**替代案**：要求 `register_started_at >= now()` 或 `started_at >= now()`。

**理由**：允許管理員補建 / 修正歷史資料（例如系統上線後補錄過去的活動紀錄），不強制未來時間。若未來前台開放報名時，可在報名情境本身再檢查當下是否在報名窗內——屬另一個需求。

## Risks / Trade-offs

- **[風險]** 後續若想加報名 / 名額管理，`conferences` 表結構可能需要擴充（例如 `capacity`、關聯 `conference_registrations`）。→ **緩解**：維持本表欄位極簡，之後以 migration 擴充而非重建。
- **[風險]** 使用者建立錯誤的時間資料後，因無刪除功能，只能以 status = Inactive 掩蓋。→ **緩解**：修改功能已足以更正資料；無刪除是刻意的，不是疏漏。
- **[Trade-off]** `int unsigned` 比 `bigint` 省 4 bytes，但與專案其他 `bigint` 主鍵不一致；接 join 時需注意型別對齊。→ **緩解**：本表獨立、無外鍵互通，影響範圍為零。

## Migration Plan

1. 以 `php artisan migrate`（Docker 內）建立 `conferences` 表。
2. 於權限註冊處補上三個 permission key，執行對應的權限 seeder 或同步指令。
3. 部署後授權合適的角色使用 `Conference.*`。
4. 無資料回填需求（新表）。
5. **Rollback**：若要回退，移除路由、view、controller 引用後 `php artisan migrate:rollback` 即可；因無外部資料相依，rollback 無資料遺失風險（除了說明會本身的內容）。

## 已知事項

- **權限註冊位置**：[database/seeders/PermissionSeeder.php:14-82](database/seeders/PermissionSeeder.php#L14-L82) 的 `$modules` 陣列。新增 `'Conference' => ['label' => '說明會', 'actions' => ['index' => '列表', 'create' => '新增', 'update' => '編輯']]`，Docker 內執行 `php artisan db:seed --class=PermissionSeeder` 即會 sync 入 `permissions` 表並同步既有 role 的權限。
- **Blade 版型對照**：參考 `resources/views/admin/addons/*.blade.php` 的 `@extends` / `@section` 結構與 CSS 類名。
- **Controller permission attribute**：`#[RequiresPermission]` 為 `TARGET_METHOD`（見 [app/Attributes/RequiresPermission.php:13](app/Attributes/RequiresPermission.php#L13)），**必須** 放在每個 action method 上，不可放在 class 上。Action → permission 對應：`index → Conference.index`、`create/store → Conference.create`、`edit/update → Conference.update`（對齊 [AddonController.php:19-82](app/Http/Controllers/AddonController.php#L19-L82) 慣例）。
- **未授權行為**：[CheckPermission middleware](app/Http/Middleware/CheckPermission.php#L34-L42) 實際會將缺權限使用者 `redirect` 至其 `default_route`（多為 `Dashboard.index`）而非直接回 403，僅在 default_route 也無對應時才 `abort(403)`。測試時使用 `assertRedirect()` 而非 `assertStatus(403)`。
