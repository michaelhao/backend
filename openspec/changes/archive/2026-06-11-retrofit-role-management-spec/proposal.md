# retrofit-role-management-spec

## Why

角色管理（roles CRUD + 自製權限系統）歷經兩個階段演進——原始實作計畫 [spec/3-permission-system.md](../../spec/3-permission-system.md)、安全審查補強 [spec/15-role-security-review.md](../../spec/15-role-security-review.md)（session 權限即時撤銷、`default_route` 路由解析驗證、移除 remember-me 連動），以及 2026-06-12 的設計 review 修正（commit `bdf668a` 分層重構 / dead code 清理、commit `ce741b3` transaction 包覆、commit `4b2091d` PermissionFactory + failure 測試）——但 `openspec/specs/` 至今沒有 role-management capability，文件與現狀脫節，且舊版 spec 檔的部分內容（如「permissions 須包含 default_route」的驗證設計）已與實作不符。

本 change 為**文件回填**：將兩個階段的內容與設計 review 修正後的實際行為合併為一份反映現狀的 `role-management` capability spec，並汰除舊版 spec 檔。

## What Changes

- 新增 `role-management` capability spec，涵蓋現狀行為：
    - 角色 CRUD（列表計數、驗證規則、redirect-with-flash、刪除保護與 JSON 回應、transaction 一致性）
    - `default_route` 雙重驗證（permission 存在 + 可解析至實際命名路由）與權限自動補入
    - 權限檢查 middleware（`#[RequiresPermission]` attribute 優先、fallback 自動推導、closure route 403、無角色 / 無權限導向）
    - Session 權限快取與版本戳即時撤銷（`max(users.updated_at, roles.updated_at)`）
    - UI 權限控制（`<x-permission>` component）與 `PermissionSeeder` sync 機制
    - 刻意不做的設計：不防自我提權、無操作稽核 log、不引入外部權限套件（內部後台威脅模型）
- 刪除 `spec/3-permission-system.md` 與 `spec/15-role-security-review.md`（由本 spec 取代）
- **無程式變更**：對應實作已在 commit `bdf668a`、`ce741b3`、`4b2091d` 完成並通過 PermissionTest（27 passed）

## Capabilities

### New Capabilities
- `role-management`: 角色 CRUD 與自製權限系統（attribute-based middleware、session 權限快取、即時撤銷）的完整行為規格（內部後台、非公網暴露的威脅模型）

### Modified Capabilities
<!-- 無。 -->

## Impact

- **程式碼**：無變更（純文件）。spec 描述的現狀實作位於 `app/Http/Controllers/RoleController.php`、`app/Services/RoleService.php`、`app/Services/PermissionRouteResolver.php`、`app/Repositories/RoleRepository.php`、`app/Repositories/PermissionRepository.php`、`app/Http/Middleware/CheckPermission.php`、`app/Models/Traits/HasPermissions.php`、`app/Attributes/RequiresPermission.php`、`database/seeders/PermissionSeeder.php`
- **文件**：新增 `openspec/specs/role-management/spec.md`（archive 後）；刪除 `spec/3-permission-system.md`、`spec/15-role-security-review.md`
- **測試**：無變更。spec 的 Scenario 與既有測試一一對應（`tests/Feature/PermissionTest.php` 27 案例）
