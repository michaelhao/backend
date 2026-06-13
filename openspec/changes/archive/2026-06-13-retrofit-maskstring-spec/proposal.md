# retrofit-maskstring-spec

## Why

顯示遮蔽工具（`App\Support\Mask`：`string()` 奇數索引遮蔽、`email()` local part 遮蔽）的文件仍停留在原始實作計畫 [spec/8-maskstring-support.md](../../../spec/8-maskstring-support.md)，而該舊 spec 描述的是**過渡狀態**：「保留 `app/Helpers/MaskHelpers.php` 全域 `maskString()`/`maskEmail()` 作為 delegate 維持向後相容」。實際演進已超前舊 spec——commit `816ad29` 將邏輯遷入 `App\Support\Mask` 靜態類別，commit `186da22` 進一步把 `MaskHelpers.php` **整檔移除**，PHP 端全部改用 `Mask::`。舊 spec 引用的行號（blade 113/146、test 78）亦已漂移。`openspec/specs/` 至今沒有 string-masking capability（僅 `shop-management` spec 引用 `Mask::` 的使用情境）。

2026-06-13 設計 review 後補了 2 項（commit `b81e784`、`9f8083a`）：`App\Support\Mask` 原本無專屬單元測試（唯一觸及處為 `ShopRuTest` 以 Mask 自算期望值的套套邏輯）；前端 `edit.js` 的 `maskString()` 與後端為同演算法的雙端實作，原無一致性註解。

本 change 為**文件回填**：將舊 spec 與演進後 + review 修正後的實際行為合併為一份反映現狀的 `string-masking` capability spec，並汰除被取代的舊 spec 檔。

## What Changes

- 新增 `string-masking` capability spec，涵蓋現狀行為：
    - `Mask::string()`：奇數索引換 `*`、以字元為單位（多位元組安全）；空字串 / 單字元 / 中文等邊界
    - `Mask::email()`：僅遮 `@` 前 local part、保留網域、無 `@` 退回整串遮蔽；短 / 單字元 / 空 local part 等邊界
    - 刻意設計（MUST NOT）：不以全域函式提供遮蔽（佐證 `186da22` 移除 `MaskHelpers.php`）、`email()` 內部呼叫 `string()` 使依賴明確
    - 設計約束：前端 `edit.js` 的 `maskString()` 與後端 `Mask::string()` 須同演算法
    - 刻意設計（MUST NOT）：遮蔽僅為顯示層、真值仍隨頁面送至前端，非機密控制（符合內部後台威脅模型）
- 刪除 `spec/8-maskstring-support.md`（由本 spec 取代）
- 本文件階段對應的程式變更已於 commit `b81e784`（補 `MaskTest` 單元測試）、`9f8083a`（`edit.js` 雙端一致性註解）完成

## Capabilities

### New Capabilities
- `string-masking`: `App\Support\Mask` 顯示遮蔽工具（字串奇數索引遮蔽、email local part 遮蔽）的完整行為規格與刻意設計決策（內部後台、非公網暴露的威脅模型；本功能無任何外部呼叫）

### Modified Capabilities
<!-- 無。Mask 在 shops 編輯頁的使用情境已由 shop-management spec 規範，本 change 僅以 cross-reference 連結，不修改其 spec。 -->

## Impact

- **程式碼**：spec 描述的現狀實作位於 `app/Support/Mask.php`；使用點為 `resources/views/admin/shops/edit.blade.php`（管理員 email、統一編號遮蔽顯示）與前端孿生 `resources/js/shops/edit.js`
- **文件**：新增 `openspec/specs/string-masking/spec.md`（archive 後）；刪除 `spec/8-maskstring-support.md`
- **測試**：spec 的 `Mask` Scenario 與 `tests/Unit/Support/MaskTest.php` 一一對應；顯示層使用情境另由 `tests/Feature/ShopRuTest.php` 涵蓋
