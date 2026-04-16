# 加購功能系統規格書 (Final Version)

## 0. 建立新分支
feat/addon-system

## 1. 資料庫模型 (Data Models)


### `addons` (主功能定義)
| 欄位名 | 類型 | 說明 |
| :--- | :--- | :--- |
| id | int | Primary Key |
| type | tinyint | 1: feature, 2: quota (`AddonType`) |
| name | varchar(50) | 功能名稱 |
| price | int unsigned | 售價 (目前無小數點需求) |
| unit | varchar(10) | 單位 (nullable) |
| status | tinyint | **-1: 已刪除**, 0: 下架, 1: 上架 (`AddonStatus`) |
| **syncing** | tinyint | 0: 完成, 1: 同步中 (`AddonSyncing`) |
| created_at | datetime | |
| updated_at | datetime | |

**Enum: `AddonSyncing`**
| Case | Value | 說明 |
| :--- | :--- | :--- |
| Done | 0 | 同步完成（預設） |
| Syncing | 1 | Grade Job 執行中 |

### `addons_image` (單圖需求)
| 欄位名 | 類型 | 說明 |
| :--- | :--- | :--- |
| id | int | Primary Key |
| addon_id | int | 關聯 addons.id，**UNIQUE** |
| image_url | varchar(255) | 圖片路徑 |
| created_at | datetime | |
| updated_at | datetime | |

> `addon_id` 加 UNIQUE constraint，確保單圖邏輯由 DB 層保障，防止 race condition。

### `grades_addons` (等級預設功能)
| 欄位名 | 類型 | 說明 |
| :--- | :--- | :--- |
| id | int | Primary Key |
| grade_id | int | 等級 ID |
| addon_id | int | 關聯 addons.id |
| created_at | datetime | |
| updated_at | datetime | |

> UNIQUE(`grade_id`, `addon_id`)

### `shops_addons` (商店實際持有功能)
| 欄位名 | 類型 | 說明 |
| :--- | :--- | :--- |
| id | int | Primary Key |
| shop_id | int | 商店 ID |
| addon_id | int | 關聯 addons.id |
| **source** | tinyint | 1: Grade (等級預設), 2: Purchased (手動加購) |
| status | tinyint | 0: 停用, 1: 啟用 |
| **expired_at** | **datetime** | 過期時間 (nullable)，精確到 23:59:59 |
| created_at | datetime | |
| updated_at | datetime | |

> - UNIQUE(`shop_id`, `addon_id`)
> - INDEX(`shop_id`, `source`) — 高頻查詢組合
> - INDEX(`addon_id`) — 刪除/同步查詢使用

### `shops`
新增 | **expired_at** | **date** | 過期時間 (nullable) |

---

## 2. 核心邏輯說明

### 1. 商店等級變更比對 (Sync Logic)

當商店 (Shop) 的 grade_id 從 A 變更為 B 時，針對 source = 1 的項目執行以下邏輯：

- A. 比對程序
1. 取得現有集合：找出該商店目前 source = 1 的所有 addon_id (集合 $S_{old}$)。
2. 取得目標集合：找出新等級 對應 grades_addons 的所有 addon_id (集合 $S_{new}$)。
3. 計算差集：要移除的 (To Remove)：$S_{old} - S_{new}$。要新增的 (To Add)：$S_{new} - S_{old}$。

- B. 執行動作
1. To Remove：set source = 2 & expired_at 壓上**當日時間 23:59:59**。
2. To Add：
   - 若該商店已有 source = 2 的相同 addon → 升級：set source = 1 & expired_at = null（從購買變成版本所屬）。
   - 不存在 → 新增，標記 source = 1。
3. 獨立加購項 (source = 2) 不在 To Add 範圍內的：完全不參與比對，維持原狀，確保商店權益。


* **Addon/Grade 關聯變動時**：
    * 當後台調整某個 Addon 歸屬的 Grade 時，須同步對該等級下的所有商店進行 `shops_addons` (source=1) 的新增或刪除。


### 2. Addon 歸屬 Grade 變更比對

當 Addon 的歸屬 Grade 有新增/刪除時，針對 grades_addons & shops_addons 的項目執行以下邏輯：

1. 更新 grades_addons（新增/刪除對應記錄）。
2. 對受影響 Grade 下的所有商店執行 shops_addons 同步（走 Queue，見第 4 節效能說明）：
   1. 該被移除的：set source = 2 & expired_at 壓上**當日時間 23:59:59**。
   2. 要新增的：
      - 若商店已有 source = 2 的相同 addon → 升級：set source = 1 & expired_at = null。（從購買變成版本所屬）
      - 不存在 → 新增，標記 source = 1。
   3. 其餘獨立加購項 (source = 2)：維持原狀。

> **Addon 歸屬管理入口**：在 Addon 的建立/編輯頁面，透過 `grade_ids[]` 多選欄位設定歸屬版本（可為空，代表不屬於任何版本）。


### 3. 狀態與刪除機制
**主表 (addons)：**
刪除時執行軟刪除，將 status 更新為 -1，用於保留名稱與價格的歷史紀錄。

**關係表 (grades_addons & shops_addons)：**
當 Addon 被標記為 -1，或手動解除關聯時，直接物理刪除 (DELETE) 對應的關係紀錄。
商店端的 source = 2 紀錄若因 Addon 刪除而需要移除，同樣直接 DELETE。

**刪除事務的原子性**
```
當 addons.status 更新為 -1 時，必須確保與 grades_addons 和 shops_addons 的 DELETE 動作在同一個 Database Transaction 內完成。
```

### 4. 效能與非同步同步機制

**風險**：當後台調整某個 Addon 歸屬的 Grade 時，須同步對受影響等級下所有商店進行新增或刪除。若等級下有萬家商店，DB 負擔極重。

**策略**：
- **Queue**：同步邏輯不在 Web Request 中執行，丟入 Background Job。
- **批次處理**：使用 `upsert` 進行批次寫入，避免迴圈內執行 SQL。

**同步狀態追蹤（Laravel Job Batching）**：

受影響的 Grade 可能有多個，對應多個 `SyncShopAddonsForGrade` Job。使用 `Bus::batch()` 統一追蹤所有 Job 的完成狀態：

```
Transaction:
  1. 更新 grades_addons
  2. addons.syncing = 1 (AddonSyncing::Syncing)
  afterCommit:
    Bus::batch([
        SyncShopAddonsForGrade(Grade B),
        SyncShopAddonsForGrade(Grade C),
    ])
    ->name('Addon grade sync')           // 顯示於 job_batches.name
    ->then(fn() => addon.syncing = 0)   // 全部成功
    ->catch(fn() => addon.syncing = 0)  // 失敗也解除，避免永久卡住
    ->dispatch()
```

**Queue 名稱**：`SyncShopAddonsForGrade` 使用獨立 queue `addons`，不混入 `default`。
```bash
php artisan queue:work --queue=addons
```

**job_batches 清理**：批次執行完畢後資料不會自動刪除，需排程定期清理：
```php
// routes/console.php
Schedule::command('queue:prune-batches --hours=48')->daily();
```

**Addon 頁面顯示**（不需跨頁至 Grade 頁確認）：
- `syncing = 1` → 顯示「同步中...」badge
- `syncing = 0` → 顯示「已同步」或不顯示
- 若同步失敗（catch 觸發）→ 管理員重新儲存即可重新 dispatch

**Job 冪等性**：
- Job 執行前確認 Addon `status != -1`，若已被刪除則跳過。
- 批次 UPDATE 條件限制只處理符合狀態的記錄，確保重試安全。

### 5. 圖片處理規範
* **允許格式**：僅接受 `jpg`、`png`。
* **命名規則**：上傳圖片時，檔名強制改寫為 `{addon_id}-img-{timestamp}.{ext}`，副檔名保留原始格式（jpg 存 jpg、png 存 png）。
* **儲存位置**：`Storage::disk('public')` → 實際路徑 `storage/app/public/addons/{filename}`。
* **單圖邏輯**：每個 Addon 僅對應一筆 `addons_image` 紀錄。
* **更新順序**：先存新圖，成功後再刪舊圖（避免存檔失敗造成無圖狀態）。
* **URL 生成**：Blade 使用 `asset('storage/' . $image_url)`，不使用 `Storage::disk('public')->url()`。後者會硬編碼 `APP_URL`，在 Docker 環境中可能與瀏覽器存取的 host 不符導致破圖。
* **Symlink**：部署後需執行 `php artisan storage:link`，確保 `public/storage` → `storage/app/public`。

---

## 3. CRUD 開發規範

### Read (讀取)

**分頁控制**：支援 `per_page` 參數，選項固定為 `[50, 100, 150, 200]`，預設 50。

**列表條件**：預設排除 `status = -1` 的項目，除非有特殊歷史查詢需求。

**顯示內容**：圖片縮圖、名稱、類型、狀態、價格、所屬版本、同步狀態（syncing）、操作。

**搜尋區塊版型**（參考商店管理排版）：

```
┌─────────────────────────────────────────────────┐
│  關鍵字（名稱）  │  類型  │  狀態  │  所屬版本   │  ← 條件列（grid）
├─────────────────────────────────────────────────┤
│  [搜尋]  清除                   每頁顯示 [50▼] 筆 │  ← 按鈕列
└─────────────────────────────────────────────────┘
```

- 條件欄位：
  - `keyword`：名稱模糊搜尋
  - `type`：類型 select（全部 / feature / quota）
  - `status`：狀態 select（全部 / 上架 / 下架）
  - `grade_id`：所屬版本 select（全部 / 各 Grade）
- **搜尋**：submit 表單
- **清除**：`<a href="{{ route('addons.index') }}">清除</a>`，回到無條件列表
- per_page select 使用獨立隱藏表單，切換時保留現有篩選條件（同商店管理實作）

### Create / Update (建立與更新)
* **事務處理 (Transaction)**：所有涉及多表的異動 (Addons, Images, Grades 關聯) 必須包裹在 Transaction 中。
* **Grade 同步**：Addon 的 `grade_ids` 變動時，對受影響的 Grade 各自 dispatch 一個 `SyncShopAddonsForGrade` Job。
* **存在性檢查**：在 Create/Update shops_addons 時，後台必須檢查 addon_id 是否真實存在且 `status != -1`。

### Delete (刪除)
* 執行 **Update `status = -1`**（軟刪除）。
* 在同一 Transaction 內物理刪除 `grades_addons` 與 `shops_addons` 中對應的所有關聯記錄。

---

### 權限

PermissionSeeder 新增 `Addon` module：

| 動作 | 說明 |
| :--- | :--- |
| index | 列表 |
| create | 新增 |
| update | 編輯 |
| delete | 刪除 |

---

### Seeder

**ShopSeeder**：每筆 Shop 新增 `expired_at` 欄位，值為 `now()+1month`。

**AddonSeeder**：

| name | type | price | unit | status |
| :--- | :--- | :--- | :--- | :--- |
| 功能 1 | 1 | 8000 | null | 1 |
| 功能 2 | 1 | 7000 | null | 1 |
| 功能 3 | 1 | 6000 | null | 1 |
| 功能 4 | 1 | 5000 | null | 1 |
| 功能 5 | 1 | 4000 | null | 1 |
