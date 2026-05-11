## ADDED Requirements

### Requirement: 全站時區為 Asia/Taipei

`config('app.timezone')` SHALL 為 `'Asia/Taipei'`。所有 Carbon `now()`、`today()`、`whereDate()` 與 datetime cast 在無顯式 timezone 參數時 SHALL 以 `Asia/Taipei` 解讀。

#### Scenario: config 值為 Asia/Taipei
- **WHEN** 讀取 `config('app.timezone')`
- **THEN** 回傳字串 `'Asia/Taipei'`

#### Scenario: now() 為 Asia/Taipei
- **WHEN** 呼叫 `Carbon::now()`
- **THEN** 回傳 Carbon instance 的 timezone 為 `Asia/Taipei`

#### Scenario: today() 邊界為 Asia/Taipei
- **WHEN** 呼叫 `Carbon::today()`
- **THEN** 回傳 Carbon instance 為 `Asia/Taipei` 當日 00:00:00

---

### Requirement: 歷史資料不 migrate

DB 中既有 datetime 欄位字串值 SHALL 不變。本 change 不寫資料 migration。

#### Scenario: 既有 row 字串值不變
- **GIVEN** DB 中有 row `shops.expired_at = '2026-12-31 23:59:00'`（在改 config 之前寫入）
- **WHEN** 改 config 並重新啟動
- **THEN** DB 中該 row 字串值仍為 `'2026-12-31 23:59:00'`
- **AND** 讀取為 Carbon 時 timezone 為 `Asia/Taipei`，等同 `2026-12-31 15:59 UTC`
