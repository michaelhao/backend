# design — retrofit-maskstring-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit，本 change 只產出 spec 文件並補上 review 階段的測試與註解。來源：舊版 [spec/8-maskstring-support.md](../../../spec/8-maskstring-support.md) 與 2026-06-13 設計 review。對應程式 commit：`816ad29`（遷入 `App\Support\Mask`）、`186da22`（移除 `MaskHelpers.php`）、`b81e784`（補 `MaskTest`）、`9f8083a`（`edit.js` 一致性註解）。

## Goals / Non-Goals

**Goals:**
- 一份 `string-masking` capability spec 完整描述 `Mask::string()` / `Mask::email()` 的可觀察行為與邊界
- 每個 `Mask` Scenario 對應一個 `MaskTest` 案例，spec 可被測試驗證
- 記錄「刻意不做 / 刻意如此」的設計（不提供全域 helper、遮蔽僅為顯示層）與其依據

**Non-Goals:**
- 不規範 `Mask` 在商店編輯頁的渲染細節（屬 shop-management，已規範），僅以 cross-reference 連結
- 不擴充 `Mask` 的遮蔽策略（如可設定遮蔽字元、固定長度遮蔽）——非現狀，不臆測

## Decisions

1. **靜態類別取代全域 helper**：舊 spec 描述保留 `MaskHelpers.php` 全域函式作 delegate；實作最終（`186da22`）整檔移除，PHP 端一律走 `App\Support\Mask`。理由：全域函式不在 PSR-4 namespace，IDE 無法 go-to-definition、靜態分析無法建立 `email()→string()` 呼叫圖。spec 以最終狀態為準，並把「不保留全域 helper」寫成 MUST NOT Requirement。

2. **`email()` 以 `@` 位置切分、委派 `string()`**：local part 套 `Mask::string()`、網域原樣保留；無 `@` 退回整串遮蔽。因 `@`（ASCII）在 UTF-8 為自我同步位元組，以位元組 `strpos`/`substr` 切分仍落在字元邊界，local part 再交由 `mb_*` 的 `string()` 處理，故整體多位元組安全。

3. **遮蔽為顯示層、非機密控制**：商店編輯頁同時保留遮蔽顯示值與真值（可編輯 / hidden input），供有權限者修改。依威脅模型（內部後台、能登入即受信任內部人員），遮蔽僅降低肩窺風險，不作機密邊界。寫成 MUST NOT Requirement 避免日後被誤當存取控制強化。

4. **前後端雙端演算法**：認證流程在前端即時遮蔽（`edit.js` 的 `maskString`），重新載入則由後端 `Mask::string` 渲染；兩端必須同演算法。JS 無法引用 PHP，重複不可免，改以 `edit.js` 註解指回 `App\Support\Mask` + spec 設計約束守護（`9f8083a`）。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → `Mask` 行為的每個 Scenario 綁定 `MaskTest` 案例，行為變更時測試先失敗，提醒同步更新 spec
- [前後端遮蔽邏輯無自動化一致性測試] → 接受：純函式、跨語言難以單一測試守護；以註解 + 設計約束 Requirement 降低漏改風險
- [單字元 local part（如 `a@b.com`）完全不遮蔽] → 接受：奇數索引規則的自然結果，已以 Scenario 明列為已知邊界
