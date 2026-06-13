# addon-management Specification (delta)

## MODIFIED Requirements

### Requirement: 刪除採軟刪除並清除關聯

`DELETE /addons/{id}`（需 `Addon.delete`）SHALL 對主表執行軟刪除（`status = -1`，保留名稱與價格歷史），並在**同一資料庫交易內物理刪除** `grades_addons`、`shops_addons`、`addons_image` 中對應的所有關聯列。找不到或已刪除的項目 SHALL 回 **404** JSON「找不到該附加功能」（2026-06-14 設計 review 裁示 not-found 一致化為 404，與 role / user 對齊）。共用前端刪除互動見 [[delete-confirmation]]。

#### Scenario: 軟刪除主表
- **GIVEN** 一筆上架附加功能
- **WHEN** DELETE `/addons/{id}`
- **THEN** 回 JSON 成功訊息，該 addon `status = -1`（仍存在於資料表）

#### Scenario: 刪除清除 grades_addons 關聯
- **GIVEN** 一筆關聯某版本的附加功能
- **WHEN** DELETE `/addons/{id}`
- **THEN** `grades_addons` 對應列被物理刪除

#### Scenario: 刪除清除 shops_addons 關聯
- **GIVEN** 一筆已被商店持有的附加功能
- **WHEN** DELETE `/addons/{id}`
- **THEN** `shops_addons` 對應列被物理刪除

#### Scenario: 刪除不存在的附加功能
- **WHEN** DELETE `/addons/99999`
- **THEN** 系統回應 404 JSON「找不到該附加功能」
