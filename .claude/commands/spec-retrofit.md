---
name: "Spec: Retrofit"
description: 既有舊功能回填流程：設計 Review → 裁示後修正 → OpenSpec spec 回填 → 互動式 HTML 文件（新功能請走 opsx:propose → spec-build → spec-ship）
category: Workflow
tags: [workflow, openspec, review, retrofit, docs]
---

完整「設計 Review → 裁示 → 修正 → spec 回填 → HTML 文件」流程，把**已有實作但 spec 還停留在舊版 `spec/*.md`** 的功能補齊到 openspec + docs。流程萃取自 auth 功能的回填（commit `4adf510`、`9339bf4`、`1734ff7`、`73a1193`）。

**僅限既有舊功能**。新功能一律走 `/opsx:propose → /spec-build → /spec-ship`，不套此流程。

**Input**: 既有功能名稱（如 `roles`、`grades`、`users`、`shops`、`bills`、`addons`、`conferences`）。從 `routes/web.php`、`app/Http/Controllers/`、`spec/*.md` 推斷該功能範圍；範圍模糊時用 AskUserQuestion 確認。

**前置檢查**：目標功能必須已有實作（routes 與 Controller 存在）。若是尚未實作的新功能 → **停下**，提示改走 `/opsx:propose`，不繼續。

---

## Steps

### Phase 1 — 全面性設計 Review（結束後必停）

讀取該功能的全部相關檔案：routes、Controller、FormRequest、Service、Repository、Model、views、既有測試、舊 `spec/*.md`。依以下軸線審查：

1. **分層架構合規**（CLAUDE.md）：Service 不得碰 HTTP/session/Request、Repository 封裝所有 Eloquent 查詢、skinny controller、輸入驗證走 FormRequest
2. **安全**（依專案 threat model）：內部後台、非公網暴露 → brute-force 類**不審**；正式環境無對外網路 → 任何外部 API 呼叫（HIBP 等）都是 finding
3. **測試覆蓋**：happy / failure / edge 三類；缺測試本身就是 finding
4. **行為與舊 spec 落差**：實作與 `spec/*.md` 不一致處列出；**刻意設計**（如 auth 的「無 remember me」）需識別為設計決策，不得當 bug

產出格式：編號 findings（A、B、C…），每項含「問題 / 影響 / 具體建議」。

**停下** → 用 AskUserQuestion（multiSelect）讓使用者勾選要修的項目。未經裁示**不得**進 Phase 2。

### Phase 2 — 修正（僅核可項目）

逐項處理使用者勾選的 findings：

- Goal-driven：每項先寫測試重現問題或驗證目標行為，再修到綠
- 每項修完必跑：

```bash
docker compose exec backend-api vendor/bin/pint --dirty --format agent
docker compose exec backend-api php artisan test --compact --filter=<keyword>
```

- 依邏輯分 commit（重構與行為修正分開），commit 前 verify 訊息對 staged diff

### Phase 3 — OpenSpec spec 回填

走 opsx change 流程，把舊 spec + 修正後的實際行為合併成正式 capability spec：

```bash
openspec new change retrofit-<feature>-spec   # 或依 opsx:propose 產 artifacts
openspec validate <change-name>
openspec archive <change-name> --yes          # merge 進 openspec/specs/<capability>/spec.md
```

- 內容 = 舊 `spec/*.md` + Phase 2 修正後的實際行為
- Requirement/Scenario 用 SHALL / MUST NOT + GIVEN/WHEN/THEN，範本：`openspec/specs/auth/spec.md`
- **刻意不做的設計也要寫成 MUST NOT Requirement**（含原因與佐證，如 migration 名稱）
- Purpose 段落寫入 threat model 摘要與來源（被合併的舊檔名、相關 commit）
- 刪除被取代的舊 `spec/*.md`，commit

### Phase 4 — 互動式 HTML 文件

從 `openspec/specs/<capability>/spec.md` 產生 `docs/<capability>-spec.html`，範本：`docs/auth-spec.html`。

硬性規格：

- **單檔自包含、零外部資源**（正式環境無對外網路：無 CDN、無外部字型/圖片）
- `lang="zh-Hant"`、inline CSS + vanilla JS
- 深色 sidebar TOC + scroll-spy、需求卡片依群組配色、SHALL / MUST NOT badge
- 可摺疊 Scenario（GIVEN/WHEN/THEN 彩色標籤）、每個 Scenario 附對應測試參照
- 「刻意不做」獨立區塊、展開/收合全部按鈕

驗證：

1. requirement / scenario 數量與 spec.md 一致
2. grep 確認無外部資源引用（`src=`、`href=` 不得指向外部 URL）
3. HTML 標籤平衡（可用 python HTMLParser 檢查）

`/docs` route 會自動列出（`DocsController` glob `docs/*.html`），**不需改 route**。

commit 後跑全套件確認無回歸：

```bash
docker compose exec backend-api php artisan test --compact
```

---

## Output

```
## Retrofit Complete

**Feature:** <name>
**Findings:** N 項（核可修正 M 項）
**Fixes:** <commit hashes>
**Spec:** openspec/specs/<capability>/spec.md（X Requirements / Y Scenarios，commit <hash>）
**HTML:** docs/<capability>-spec.html（commit <hash>，/docs 可瀏覽）
**Tests:** ✓ 全套件通過
```

---

## Guardrails

- **僅限既有舊功能回填**；新功能一律改走 `/opsx:propose → /spec-build → /spec-ship`
- Phase 1 結束必停等使用者裁示，未核可項目不修
- 所有 PHP/artisan/composer/test 指令走 `docker compose exec backend-api`（CLAUDE.md）
- 刻意設計不得當 bug「修掉」，要寫成 MUST NOT Requirement
- 既有測試檔不得刪除（PHPUnit 規範）
- 全程繁體中文回覆；不自動 push
- HTML 文件零外部資源（no-outbound-internet 約束）
