## Why

後台目前沒有維護「說明會」活動的地方，管理員必須手動以其他管道紀錄活動時間與報名窗。為了讓說明會資訊集中、可追蹤，需要一個獨立的 CRUD 模組管理說明會。為保留歷史紀錄，這一版刻意不做刪除。

## What Changes

- 新增後台「說明會管理」模組，支援：列表瀏覽、新增、修改。
- 透過 `status`（Active / Inactive）控制上下架；**不提供刪除**，歷史資料永久保留。
- 時間欄位採「活動時間 + 報名時間」雙組 datetime，FormRequest 驗證四欄位順序。
- 權限加入 `Conference.index`、`Conference.create`、`Conference.update`（無 delete）。
- 實體完全獨立，不關聯 grade / shop / user。

## Capabilities

### New Capabilities
- `conference-management`: 後台說明會實體的列表、新增、修改與上下架行為

### Modified Capabilities
<!-- 無 -->

## Impact

- **資料庫**：新增資料表 `conferences`（id / name / status / 4 個時間欄位 / `created_at` / `updated_at`（皆 `datetime`，依 CLAUDE.md migrate rules）；索引 `status`、`started_at`）。
- **程式碼**：新增 `Conference` Model、`ConferenceStatus` Enum、`ConferenceRepository`、`ConferenceService`、`ConferenceController`、`ConferenceRequest`、`ConferenceFactory`、Blade views、Feature Test。
- **路由**：[routes/web.php](routes/web.php) `permission` middleware 群組內新增 5 條路由（無 DELETE）。
- **權限註冊**：新增 3 個 permission key，比照 `Addon.*` 的註冊位置。
- **非影響範圍**：名額 / 報名、前台、外部 API、通知、狀態自動推進皆不在此版本。
