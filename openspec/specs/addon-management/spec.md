# addon-management Specification

## Purpose
附加功能管理（addons）的完整行為規格：CRUD、單圖上傳、Grade 關聯與停用版本規則、Grade-Addon 變動觸發的商店同步（Queue 批次）、配額餘額獨立過期與可用量查詢。威脅模型：內部後台、非公網暴露（brute-force 不在 scope）；正式環境無對外網際網路連線，本功能無任何外部 API 呼叫。合併自舊版 `spec/9-addons-system.md`、`spec/12-addons-balances.md` 與 2026-06-13 設計 review 修正（commit `c0d5ae9`、`ddabe18`、`a68c8d3`、`8636db0`）。購買 / 安裝觸發點屬 bill-management（見 `openspec/specs/bill-management/spec.md`）。

## Requirements
### Requirement: 權限控管

附加功能路由 SHALL 受 `auth` middleware 與 `RequiresPermission` attribute 保護：列表需 `Addon.index`、建立需 `Addon.create`、編輯與更新需 `Addon.update`、刪除需 `Addon.delete`。未登入或無對應權限者 SHALL 被導離，且資料不得被變更。

#### Scenario: 有 index 權限者可見列表
- **GIVEN** 持有 `Addon.index` 權限的 Admin 使用者
- **WHEN** GET `/addons`
- **THEN** 系統回應 200 並渲染附加功能列表

#### Scenario: Viewer 具列表權限可見列表
- **GIVEN** 持有 Viewer 角色（含 `Addon.index`）的使用者
- **WHEN** GET `/addons`
- **THEN** 系統回應 200

#### Scenario: 無 create 權限者不可見建立頁
- **GIVEN** 僅持有 Viewer 角色權限的使用者
- **WHEN** GET `/addons/create`
- **THEN** 系統回應 302 導離

#### Scenario: 無 update 權限者不可更新
- **GIVEN** 僅持有 Viewer 角色權限的使用者與一筆附加功能
- **WHEN** PUT `/addons/{id}`
- **THEN** 系統回應 302 導離且資料不變

#### Scenario: 無 delete 權限者不可刪除
- **GIVEN** 僅持有 Viewer 角色權限的使用者與一筆附加功能
- **WHEN** DELETE `/addons/{id}`
- **THEN** 系統回應 302 導離且資料不變

---

### Requirement: 附加功能列表與分頁

`GET /addons` SHALL 以 `id` 升序分頁顯示附加功能，eager load `image` 與 `grades`，並 **預設排除 `status = -1`（已刪除）** 的項目。每頁筆數 SHALL 由 `per_page` 控制，僅接受白名單 `[50, 100, 150, 200]`，非白名單值（含未帶）SHALL 回退為預設 50。

#### Scenario: 列表排除已刪除項目
- **GIVEN** 一筆 `status = -1` 的附加功能
- **WHEN** GET `/addons`
- **THEN** 該已刪除項目不出現在列表

#### Scenario: per_page 接受白名單值
- **WHEN** GET `/addons?per_page=100`
- **THEN** 每頁筆數為 100

#### Scenario: per_page 非白名單回退預設
- **WHEN** GET `/addons?per_page=999`
- **THEN** 每頁筆數回退為 50

---

### Requirement: 列表搜尋篩選

列表 SHALL 支援四個可組合的篩選條件：`keyword`（對 `name` 模糊比對）、`type`（`AddonType`：1 功能 / 2 配額）、`status`（`AddonStatus`：1 上架 / 0 下架）、`grade_id`（所屬版本，經 `grades_addons` 關聯比對）。`status = 0`（下架）為合法且非「未篩選」，篩選判斷 SHALL 以「值是否填寫」（`filled()`）為準，MUST NOT 以值的真假（truthiness）判斷，否則 `'0'` 會被誤判為未篩選而失效。

#### Scenario: 下架篩選只回下架項目
- **GIVEN** 一筆上架（status=1）與一筆下架（status=0）附加功能
- **WHEN** GET `/addons?status=0`
- **THEN** 列表只含下架項目，不含上架項目

#### Scenario: 上架篩選只回上架項目
- **GIVEN** 一筆上架與一筆下架附加功能
- **WHEN** GET `/addons?status=1`
- **THEN** 列表只含上架項目

#### Scenario: 名稱關鍵字模糊比對
- **GIVEN** 名稱含「電子發票」與不含的兩筆附加功能
- **WHEN** GET `/addons?keyword=電子發票`
- **THEN** 列表只含名稱符合者

#### Scenario: 類型篩選
- **GIVEN** 一筆 Feature 與一筆 Quota 附加功能
- **WHEN** GET `/addons?type=2`
- **THEN** 列表只含 Quota 項目

#### Scenario: 所屬版本篩選
- **GIVEN** 一筆關聯某版本與一筆未關聯的附加功能
- **WHEN** GET `/addons?grade_id={id}`
- **THEN** 列表只含關聯該版本者

---

### Requirement: 建立附加功能

`POST /addons`（需 `Addon.create`）SHALL 以 `AddonRequest` 驗證：`type` 必填且為 `AddonType`、`name` 必填且 ≤ 50 字、`price` 必填整數且 ≥ 0、`unit` 選填 ≤ 10 字、`status` 必填且僅允許上架（1）或下架（0）——MUST NOT 接受已刪除（-1）。建立 SHALL 在單一資料庫交易內寫入 `addons`、（若有圖片）`addons_image` 與（若有 `grade_ids`）`grades_addons`。

#### Scenario: 成功建立
- **WHEN** POST `/addons` 帶合法欄位
- **THEN** 系統 302 導回列表並 flash success，`addons` 新增該筆

#### Scenario: 帶 grade_ids 寫入 grades_addons
- **GIVEN** 一個 Active 版本
- **WHEN** POST `/addons` 帶該版本於 `grade_ids`
- **THEN** `grades_addons` 新增 grade 與 addon 的對應列

#### Scenario: 非法 type 被擋
- **WHEN** POST `/addons` 帶 `type = 99`
- **THEN** session errors 含 `type`

#### Scenario: status 不接受已刪除值
- **WHEN** POST `/addons` 帶 `status = -1`
- **THEN** session errors 含 `status`

---

### Requirement: 編輯附加功能

`GET /addons/{id}/edit` 與 `PUT /addons/{id}`（需 `Addon.update`）SHALL 先解析 addon；**找不到或 `status = -1`（已刪除）SHALL 一律視為不存在**，回 302 導回列表並 flash error。更新 SHALL 在單一交易內套用欄位變更、圖片異動與 `grade_ids` 同步。

#### Scenario: 成功更新
- **GIVEN** 一筆既有附加功能
- **WHEN** PUT `/addons/{id}` 帶合法欄位
- **THEN** 系統 302 導回列表並 flash success，資料已更新

#### Scenario: 已刪除項目不可進入編輯頁
- **GIVEN** 一筆 `status = -1` 的附加功能
- **WHEN** GET `/addons/{id}/edit`
- **THEN** 系統 302 導回列表並 flash error

#### Scenario: 已刪除項目不可更新
- **GIVEN** 一筆 `status = -1` 的附加功能
- **WHEN** PUT `/addons/{id}`
- **THEN** 系統 302 導回列表並 flash error，資料不變

---

### Requirement: 刪除採軟刪除並清除關聯

`DELETE /addons/{id}`（需 `Addon.delete`）SHALL 對主表執行軟刪除（`status = -1`，保留名稱與價格歷史），並在**同一資料庫交易內物理刪除** `grades_addons`、`shops_addons`、`addons_image` 中對應的所有關聯列。找不到或已刪除的項目 SHALL 回 422 JSON。

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

---

### Requirement: 圖片處理規範

附加功能 SHALL 最多對應一筆 `addons_image`（`addon_id` 加 UNIQUE 約束，由 DB 層保障單圖）。上傳圖片 SHALL 僅接受 `jpg` / `png`（≤ 5 MB），檔名 SHALL 強制改寫為 `{addon_id}-img-{timestamp}.{ext}` 並以 `Storage::disk('public')` 存於 `addons/` 目錄。更新圖片 SHALL **先寫入新圖、成功後才於交易提交後刪除舊圖**（避免存檔失敗造成無圖）；`remove_image=1` 且未同時上傳新圖時 SHALL 刪除圖片列與實體檔；同時上傳新圖時以新圖為主、忽略 `remove_image`。Blade SHALL 以 `asset('storage/' . $image_url)` 生成 URL。

#### Scenario: 拒絕非 jpg/png 格式
- **WHEN** POST `/addons` 帶 `gif` 圖片
- **THEN** session errors 含 `image`

#### Scenario: 接受 jpg 並寫入 addons_image
- **WHEN** POST `/addons` 帶合法 jpg 圖片
- **THEN** `addons_image` 新增對應該 addon 的列

#### Scenario: remove_image 刪除既有圖片
- **GIVEN** 一筆已有圖片的附加功能
- **WHEN** PUT `/addons/{id}` 帶 `remove_image=1` 且不帶新圖
- **THEN** `addons_image` 對應列被刪除、實體檔被移除

#### Scenario: 同時上傳新圖時忽略 remove_image
- **GIVEN** 一筆已有圖片的附加功能
- **WHEN** PUT `/addons/{id}` 同時帶新圖與 `remove_image=1`
- **THEN** 以新圖取代，`addons_image` 仍保有一筆

#### Scenario: 無既有圖片時 remove_image 為 no-op
- **GIVEN** 一筆無圖片的附加功能
- **WHEN** PUT `/addons/{id}` 帶 `remove_image=1`
- **THEN** 更新成功且不報錯

#### Scenario: remove_image=0 保留既有圖片
- **GIVEN** 一筆已有圖片的附加功能
- **WHEN** PUT `/addons/{id}` 帶 `remove_image=0` 且不帶新圖
- **THEN** `addons_image` 對應列維持不變

---

### Requirement: Grade 關聯與停用版本規則

附加功能的所屬版本 SHALL 以 `grade_ids[]` 多選設定（可為空，代表不屬於任何版本）。**新關聯的版本 MUST 為 Active**：`AddonRequest` SHALL 擋下將未關聯的停用版本加入 `grade_ids`（回 `grade_ids.*` 驗證錯誤）；但**已關聯到當前 addon 的版本即使後來被停用，更新時 SHALL 允許保留**（否則僅改其他欄位也會被擋）。建立 / 編輯表單 SHALL 對「未關聯且非 Active」的版本 checkbox 加 `disabled` 並標示「（已停用）」，已關聯的停用版本維持可勾選。

#### Scenario: 新關聯停用版本被擋
- **GIVEN** 一個停用版本
- **WHEN** POST `/addons` 帶該停用版本於 `grade_ids`
- **THEN** session errors 含 `grade_ids.0`，addon 不被建立

#### Scenario: 已關聯的停用版本更新時保留
- **GIVEN** 一筆 addon 已關聯某版本，該版本之後被停用
- **WHEN** PUT `/addons/{id}` 的 `grade_ids` 仍含該版本
- **THEN** 更新成功無錯誤，`grades_addons` 關聯維持

#### Scenario: 表單停用未關聯的停用版本 checkbox
- **GIVEN** 一個未關聯當前 addon 的停用版本
- **WHEN** GET 該 addon 的編輯頁
- **THEN** 該版本 checkbox 為 `disabled` 並標示「（已停用）」

#### Scenario: 表單保留已關聯停用版本可勾
- **GIVEN** 一筆 addon 已關聯某版本，該版本之後被停用
- **WHEN** GET 該 addon 的編輯頁
- **THEN** 該版本 checkbox 維持可勾、不標示「（已停用）」

---

### Requirement: Grade-Addon 變動觸發商店同步

當 addon 的 `grade_ids` 在建立 / 編輯時有新增或移除（`attached` / `detached` 非空）時，系統 SHALL 於交易內把 `addons.syncing` 設為 `1`（同步中），交易提交後對每個受影響版本 dispatch 一個 `SyncShopAddonsForGrade` Job，並以 `Bus::batch` 統一追蹤。Job SHALL 使用獨立 queue `addon_sync`（不混入 `default`）。批次全部成功（`then`）或失敗（`catch`）時 SHALL 一律把 `addons.syncing` 重置為 `0`（失敗時並寫入 error log），避免旗標永久卡在「同步中」。`job_batches` SHALL 由排程 `queue:prune-batches --hours=48`（每日）清理。

#### Scenario: 建立帶 grade_ids 觸發同步批次
- **GIVEN** 一個版本
- **WHEN** POST `/addons` 帶該版本於 `grade_ids`
- **THEN** 系統 dispatch 含 `SyncShopAddonsForGrade` 的批次

#### Scenario: 更新變更 grade_ids 觸發同步批次並設 syncing
- **GIVEN** 一筆既有 addon
- **WHEN** PUT `/addons/{id}` 變更 `grade_ids`
- **THEN** 系統 dispatch 同步批次，且 `addons.syncing = 1`

#### Scenario: 批次完成重置 syncing
- **GIVEN** 一筆已觸發同步、`syncing = 1` 的 addon
- **WHEN** 批次所有 Job 成功（`then` 觸發）
- **THEN** `addons.syncing` 重置為 `0`

#### Scenario: 批次失敗仍重置 syncing 並記錄 log
- **GIVEN** 一筆已觸發同步、`syncing = 1` 的 addon
- **WHEN** 批次有 Job 失敗（`catch` 觸發）
- **THEN** `addons.syncing` 重置為 `0`，且寫入「Addon grade sync batch failed」error log

---

### Requirement: 商店 addon 同步差集規則

`ShopAddonSyncService::syncForShop(int $shopId, int[] $newAddonIds)` SHALL 在獨立 `DB::transaction` 內，對單一商店 `source = Grade` 的 addon 集合與目標集合做差集並套用：

- **要移除（舊有但不在目標）**：`source` 改 `Purchased`、`expired_at` 壓上**當日 23:59:59**（降級為獨立加購、保留商店權益）。
- **要新增（目標有但舊無）**：若該商店已有相同 addon 的 `source = Purchased` 列 → 升級為 `source = Grade`、`expired_at = null`；否則新增 `source = Grade`、`status = Enabled`、`expired_at = null`。
- **其餘獨立加購（`source = Purchased` 且不在目標）**：完全不參與比對、維持原狀。

`SyncShopAddonsForGrade` Job SHALL 取得該版本 `grades_addons` 的完整 addon 集合，對該版本下每一家商店呼叫 `syncForShop`。

#### Scenario: 同步新增版本 addon 至商店
- **GIVEN** 某版本新增一個 addon，且有商店屬於該版本
- **WHEN** Job 執行
- **THEN** 商店 `shops_addons` 新增該 addon（`source = Grade`）

#### Scenario: 同步移除版本 addon 降級為購買
- **GIVEN** 商店有 `source = Grade` 的 addon，該 addon 自版本移除
- **WHEN** Job 執行
- **THEN** 該列 `source` 改為 `Purchased`、`expired_at` 為當日 23:59:59

#### Scenario: 加入版本時升級既有購買
- **GIVEN** 商店已有 `source = Purchased` 的某 addon，該 addon 被加入商店所屬版本
- **WHEN** Job 執行
- **THEN** 該列升級為 `source = Grade`、`expired_at = null`

#### Scenario: 不在版本內的獨立加購維持原狀
- **GIVEN** 商店有一個不屬於其版本的 `source = Purchased` addon
- **WHEN** Job 執行
- **THEN** 該列維持不變

#### Scenario: 版本下無商店時 Job 為 no-op
- **GIVEN** 一個沒有任何商店的版本
- **WHEN** Job 執行
- **THEN** 不產生任何 `shops_addons` 異動

---

### Requirement: 額度餘額獨立過期與可用量查詢

`AddonType::Quota`（配額型）的每次購買 SHALL 於 `shop_addon_balances` 記錄一筆獨立的 `quantity` 與 `expired_at`（依購買日計算），不可為負。可用數量查詢 `ShopAddonBalanceRepository::getAvailableQuantity(shopId, addonId)` SHALL 回傳該商店該 addon **所有未過期（`expired_at > now`）餘額的 `quantity` 加總**。

#### Scenario: 僅加總未過期餘額
- **GIVEN** 同一商店同一 addon 有一筆已過期與一筆未過期的餘額
- **WHEN** 查詢可用數量
- **THEN** 回傳值僅含未過期那筆的 quantity

#### Scenario: 全部過期回 0
- **GIVEN** 同一商店同一 addon 的所有餘額皆已過期
- **WHEN** 查詢可用數量
- **THEN** 回傳 0

---

### Requirement: 同步 Job 冪等性來源（刻意設計）

Grade 同步的冪等性 SHALL 來自「per-grade 全集合差集」設計——Job 每次取得版本當前 `grades_addons` 的完整 addon 集合再 diff，重複執行收斂到相同結果。因此 Job **MUST NOT 依賴 per-addon 的 `status != -1` 檢查**：addon 軟刪除時其 `grades_addons` 列已於同一交易內物理刪除，版本集合自然不含已刪除 addon，無需在 Job 內逐筆驗證 addon 狀態。

#### Scenario: 重複執行同步收斂一致
- **GIVEN** 某版本的 addon 集合固定
- **WHEN** `SyncShopAddonsForGrade` 對同一商店重複執行
- **THEN** `shops_addons` 結果一致，不產生重複或殘留

---

### Requirement: 購買觸發點不在本範疇（刻意設計）

附加功能的「購買 / 安裝」流程 MUST NOT 由 addon-management 擁有。`shops_addons` 的 `source = Purchased` 寫入與 `shop_addon_balances` 餘額建立 SHALL 由 **bill-management 的付款安裝**（`BillPaymentService::installAddonDetail`）負責：加購類安裝 upsert `shops_addons`（`source = Purchased`、`status = Enabled`），`AddonType::Quota` 並同時寫入 `shop_addon_balances`。詳見 `openspec/specs/bill-management/spec.md` 的「付款安裝流程」。

#### Scenario: 加購安裝由帳單付款觸發
- **GIVEN** 一張含加購明細、轉為 paid 的帳單
- **WHEN** 付款安裝執行
- **THEN** 商店 `shops_addons` upsert 該 addon（`source = Purchased`），Quota 型並寫入 `shop_addon_balances`

---

### Requirement: 餘額不隨 addon 刪除清除（刻意設計）

`shop_addon_balances` MUST NOT 隨 addon 軟刪除或關聯解除而被 cascade delete——軟刪除僅物理刪除 `grades_addons` / `shops_addons` / `addons_image`，餘額列 SHALL 保留作為歷史紀錄。

#### Scenario: 刪除 addon 不影響餘額歷史
- **GIVEN** 某商店持有某 Quota addon 的餘額紀錄
- **WHEN** 該 addon 被軟刪除
- **THEN** `shop_addon_balances` 對應列仍保留

