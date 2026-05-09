## Context

[layouts/admin.blade.php](resources/views/layouts/admin.blade.php) 為所有 admin 頁面共用版型。Claude Design 設計稿確立淺色紫品牌方向。本次純視覺改動，**不**動 layout 結構、選單項、權限、路由。

## Goals / Non-Goals

**Goals**
- sidebar 由深色 → 淺色紫品牌
- 9 個既有 admin 頁面視覺一致
- 純 utility class 改動，不引新依賴

**Non-Goals**
- 改 layout 結構（DOM 樹維持原樣）
- 改選單項目（9 個全保留）
- mobile 響應式重做（屬另一輪）

## Decisions

### 1. 全站統一改色（不維護兩套配色）
**選擇**：直接改共用 layout，9 頁一起換色。

**替代案**：dashboard 用獨立 layout、其他頁面保留深色。

**理由**：兩套配色維護成本高且視覺斷裂。設計稿既已確立淺色紫，9 頁回歸成本可控（僅 utility class 改動）。

### 2. 9 個選單全保留
**選擇**：不採用設計稿 mock 中只示意 4 項的設計，9 項既有全保留。

**理由**：設計稿那 4 項純為視覺示意，實際選單項要對應實際 capability。

### 3. 色碼採 Tailwind 標準色（不寫死 hex）
**選擇**：active 用 `bg-violet-50 text-violet-600`、非 active 用 `text-slate-500 hover:bg-slate-100`。

**理由**：layout 是純 utility class 場景（非 inline style），Tailwind purge 不會出錯；用標準色階方便後續 dark mode 或主題切換。

### 4. active 不換字重
**選擇**：active 與非 active 都用 `font-medium`，active 只靠顏色（`bg-violet-50` + `text-violet-600`）區分。

**理由**：中文 `font-medium`（500）→ `font-bold`（700）字寬會肉眼可見變寬，切頁時 active 項抖動；紫底 + 紫字已足夠區分。

### 5. logo block 不留 border
**選擇**：移除既有 `border-b border-gray-800`，不換成淺色 border。

**理由**：淺色 admin 用 padding + 字級自然分組 logo / nav；多一條 border 反而切割視覺，少一條維護點。

### 6. top bar 灰系與 sidebar 對齊
**選擇**：scope 微擴一行，`<header>` 內所有 `gray-*` token 改為對應 `slate-*`，但紫色不下放。

**理由**：sidebar 已選 slate，top bar 仍 gray 會產生兩種近似但不同的灰相鄰；紫色保留給 sidebar active 維持「sidebar 是品牌區、top bar 是資訊列」分工。

## Risks / Trade-offs

- **[風險]** 9 頁視覺回歸：某些頁面可能因深色 sidebar 而設計了 contrast 較高的內容區，改淺色後 contrast 可能不足。**緩解**：tasks 包含逐頁檢查項目，發現問題逐頁修。
- **[Trade-off]** 純改色不重做 mobile 響應，若小螢幕 sidebar 體驗有問題，要等下個 change。

## Migration Plan

無資料 / 路由 / 權限變動。部署即生效。Rollback：revert 該 layout file。

## 已知事項（在 grill 時詳細盤點）

- 既有 layout 的 logo 區塊位置與字級（影響副標「ADMIN PANEL」要不要加 / 怎麼加）
- 既有 active 判定方式（推測為 `request()->routeIs(...)` 或 `Request::path() === ...`），改色時邏輯保持
- `<x-permission>` 過濾後選單可能少於 9 項（viewer role 等），回歸時以 admin role 為基準掃 9 項全項目
