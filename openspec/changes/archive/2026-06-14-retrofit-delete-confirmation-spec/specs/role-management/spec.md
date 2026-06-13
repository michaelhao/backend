# role-management Specification (delta)

## MODIFIED Requirements

### Requirement: 刪除角色

`DELETE /roles/{id}`（需 `Role.delete` 權限，前端以 axios 呼叫）SHALL 回應 JSON。成功時 SHALL 於單一 database transaction 內先清除 `role_has_permissions` pivot 再刪除角色，回應 200 與訊息「角色已刪除」。角色仍有使用者時 SHALL 拒絕並回應 422 與訊息「此角色仍有使用者，無法刪除」。id 不存在時回應 **404** 與訊息「找不到該角色」（not-found 與業務拒絕分流：not-found→404、業務拒絕→422；2026-06-14 設計 review 裁示一致化為 404，覆寫先前「維持現狀、不改 404」的決定，與 user-management 對齊）。共用前端刪除互動見 [[delete-confirmation]]。

#### Scenario: 成功刪除角色
- **GIVEN** 無使用者的角色
- **WHEN** DELETE `/roles/{id}`
- **THEN** 系統回應 200 JSON「角色已刪除」
- **AND** 角色已自資料庫移除

#### Scenario: 刪除後 pivot 清空
- **GIVEN** 擁有權限關聯的角色
- **WHEN** DELETE `/roles/{id}` 成功
- **THEN** `role_has_permissions` 不再有該角色的關聯列

#### Scenario: 仍有使用者的角色拒絕刪除
- **GIVEN** 仍有使用者隸屬的角色
- **WHEN** DELETE `/roles/{id}`
- **THEN** 系統回應 422 JSON「此角色仍有使用者，無法刪除」
- **AND** 角色仍存在於資料庫

#### Scenario: 刪除不存在的角色
- **WHEN** DELETE `/roles/99999`
- **THEN** 系統回應 404 JSON「找不到該角色」
