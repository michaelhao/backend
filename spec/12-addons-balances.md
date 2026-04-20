# Addon 功能擴充

----

## 背景描述

`shops_addons` 不能重複購買

但 `AddonType::Quota`（type=2）可以疊加數量

所以需要新增 table 用來記錄每次購買的餘額，每筆有獨立的 `expired_at`（依購買日期計算）

### `shop_addon_balances`

| 欄位       | 類型     | 說明                      |
| ---------- | -------- | ------------------------- |
| id         | int      | Primary Key               |
| shop_id    | int      | FK → shops(id)            |
| addon_id   | int      | FK → addons(id)           |
| quantity   | int      | 本次購入數量（不允許負數）|
| expired_at | datetime | 本筆餘額到期日（獨立計算）|
| created_at | datetime |                           |
| updated_at | datetime |                           |

INDEX (shop_id, addon_id, expired_at)

FK: shop_id → shops(id), addon_id → addons(id)  
不做 cascade delete，保留歷史紀錄

#### 獨立過期範例

```
shop_id:1  addon_id:1  quantity:1  expired_at: 2026-02-28   ← 第一次購買
shop_id:1  addon_id:1  quantity:1  expired_at: 2026-04-30   ← 第二次購買
```

可用數量（2026-03-01 查詢）= 1（第一筆已到期，第二筆仍有效）

----

## 可用數量查詢

`ShopAddonBalanceRepository::getAvailableQuantity(shopId, addonId)`

在 `shop_addon_balances` query：
- shop_id
- addon_id
- expired_at 未到期
- sum(quantity)

----

## 待實作（購買觸發點）

購買入口尚未定義，以下邏輯等觸發點 spec 確認後實作：

### 首次購買（shop_addon 不存在）

1. `shops_addons` 建立一筆（含 `expired_at`、`source = Purchased`）
2. `shop_addon_balances` 建立一筆（相同 `expired_at`、購買 quantity）

### 重複購買（shop_addon 已存在，AddonType::Quota）

1. `shops_addons.expired_at` 更新為最新到期日（不覆蓋 source）
2. `shop_addon_balances` 新增一筆（本次購買的獨立 `expired_at`）

兩張表的異動以 `DB::transaction` 包裹，失敗時一起回滾。
