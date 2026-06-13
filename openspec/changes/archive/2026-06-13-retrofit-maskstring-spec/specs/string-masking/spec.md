# string-masking Specification (delta)

## ADDED Requirements

### Requirement: 字串奇數索引遮蔽

`App\Support\Mask::string(string $value): string` SHALL 將輸入字串中索引為奇數（1、3、5…）的字元替換為 `*`，索引為偶數（0、2、4…）的字元保留原樣。計數 SHALL 以「字元」為單位（使用 `mb_strlen` / `mb_substr`），對多位元組字元安全。

#### Scenario: 一般字串遮蔽奇數位
- **WHEN** 呼叫 `Mask::string('12345678')`
- **THEN** 回傳 `1*3*5*7*`

#### Scenario: 空字串原樣回傳
- **WHEN** 呼叫 `Mask::string('')`
- **THEN** 回傳 `''`

#### Scenario: 單一字元不被遮蔽
- **GIVEN** 僅一個字元（索引 0 為偶數）
- **WHEN** 呼叫 `Mask::string('a')`
- **THEN** 回傳 `a`

#### Scenario: 多位元組字元以字元為單位遮蔽
- **WHEN** 呼叫 `Mask::string('中文字')`
- **THEN** 回傳 `中*字`（不切斷 UTF-8 位元組）

---

### Requirement: email 局部遮蔽

`App\Support\Mask::email(string $email): string` SHALL 僅對 `@` 之前的 local part 套用 `Mask::string()`，`@` 與其後網域部分 SHALL 原樣保留。輸入不含 `@` 時 SHALL 退回對整串套用 `Mask::string()`。

#### Scenario: 遮蔽 local part 並保留網域
- **WHEN** 呼叫 `Mask::email('admin@test.com')`
- **THEN** 回傳 `a*m*n@test.com`

#### Scenario: 短 local part
- **WHEN** 呼叫 `Mask::email('abc@gmail.com')`
- **THEN** 回傳 `a*c@gmail.com`

#### Scenario: 無 @ 退回整串遮蔽
- **WHEN** 呼叫 `Mask::email('no-at-sign')`
- **THEN** 回傳 `n*-*t*s*g*`（等同對整串套用 `Mask::string`）

#### Scenario: 單字元 local part 不被遮蔽
- **GIVEN** local part 僅一字元（索引 0 為偶數）
- **WHEN** 呼叫 `Mask::email('a@b.com')`
- **THEN** 回傳 `a@b.com`

#### Scenario: 空 local part
- **WHEN** 呼叫 `Mask::email('@test.com')`
- **THEN** 回傳 `@test.com`

---

### Requirement: 不以全域函式提供遮蔽（刻意設計）

遮蔽邏輯 MUST NOT 以全域函式（`maskString()`、`maskEmail()`）提供。原 `app/Helpers/MaskHelpers.php` 全域函式已由 commit `186da22` 整檔移除（前置重構 `816ad29` 先將邏輯遷入 `App\Support\Mask`）。PHP 端 SHALL 一律透過 `App\Support\Mask` 靜態類別呼叫，以利 PSR-4 autoload、IDE go-to-definition 與靜態分析建立呼叫圖。`Mask::email()` 內部 SHALL 呼叫 `Mask::string()`，使依賴關係明確可見。

#### Scenario: 程式庫不存在全域遮蔽函式
- **WHEN** 檢視程式庫
- **THEN** 不存在 `app/Helpers/MaskHelpers.php`
- **AND** 不存在全域 `maskString()` / `maskEmail()` 函式定義
- **AND** 所有 PHP 遮蔽呼叫皆為 `App\Support\Mask::string()` / `::email()`

---

### Requirement: 前後端遮蔽演算法一致（設計約束）

前端 `resources/js/shops/edit.js` 的 `maskString()` 與 `App\Support\Mask::string()` SHALL 維持相同演算法（奇數索引換 `*`）。任一端調整遮蔽規則時 MUST 同步另一端，以免認證當下前端即時顯示與重新載入後伺服端渲染不一致。此跨語言一致性無自動化測試守護，改以 `edit.js` 註解指回 `App\Support\Mask` 與本約束守護。

#### Scenario: 認證後前端遮蔽與重載後伺服端遮蔽一致
- **GIVEN** 統一編號 `12345678`
- **WHEN** 前端認證成功即時呼叫 `maskString('12345678')`，其後重新載入頁面由 `Mask::string('12345678')` 渲染同一欄位
- **THEN** 兩者皆顯示 `1*3*5*7*`

---

### Requirement: 遮蔽僅為顯示層、非機密控制（刻意設計）

`App\Support\Mask` SHALL 僅用於 UI 顯示遮蔽。系統 MUST NOT 將遮蔽視為機密邊界或存取控制：被遮蔽欄位的真值仍會隨頁面送至前端（如商店編輯頁的可編輯 input 與 hidden input）。依威脅模型（內部後台、非公網暴露、能登入後台者即為受信任之內部人員），此為可接受的刻意設計。

#### Scenario: 商店編輯頁同時含遮蔽顯示值與真值
- **GIVEN** 持 `Shop.update` 權限者開啟商店編輯頁，管理員 email 與統一編號已遮蔽顯示
- **WHEN** 檢視頁面 DOM
- **THEN** 遮蔽顯示值存在於唯讀欄位
- **AND** 對應真值仍存在於可編輯 / hidden 欄位（遮蔽不阻止有權限者取得真值）
