---
name: "Spec: Finalize"
description: 驗證 artifacts 存在 + openspec validate + review skill + 自動 commit
category: Workflow
tags: [workflow, openspec, spec]
---

完整「spec 收尾 → 驗證 → 審查 → commit」流程。**前提**：artifacts 已由 `/opsx:explore` 順勢產出（必要時已 `grill-me` 過並修訂）。

**Input**: change name (kebab-case)。若省略 → 從 active change 推斷；多個 active 時用 AskUserQuestion 詢問。

---

## Steps

### 1. 驗證 artifacts 存在

```bash
ls openspec/changes/<name>/
```

預期看到至少 `proposal.md`、`design.md`、`tasks.md`。

若目錄不存在或缺檔：**停下**，提示使用者「請先跑 `/opsx:explore <name>` 產出 artifacts」，**不**自動 propose（避免吃掉 explore 階段的設計脈絡）。

### 2. Schema 驗證

```bash
openspec validate <name> --strict
```

若驗證失敗：回報錯誤、停下，等使用者修正後可重跑本指令的 step 2 起。

### 3. LLM 審查 spec

呼叫 `review` skill（用 Skill tool，skill name = `review`）對 `openspec/changes/<name>/` 下的 spec 檔做審查。

若 review 提出修改建議：列出，等使用者裁示。使用者要求修改時，依指示改 spec，改完回到 step 2。

### 4. 產出討論稿 HTML（合併預覽）

從本次 change 觸及的每個 capability 產出**單一討論稿** `docs/<name>.html`，供 `/docs` 瀏覽分享、與他人討論。

**合併來源**（人工套用 delta 語意得「套用後整份 spec」）：

- 既有 capability（MODIFIED / REMOVED / RENAMED）：讀 `openspec/specs/<capability>/spec.md`，套用 delta（ADDED 追加 / MODIFIED 取代同名 requirement / REMOVED 移除 / RENAMED 改名）。
- 全新 capability（ADDED-only、無既有檔）：delta 的 ADDED 內容即整份。
- 多 capability 時於同一檔分節呈現。

> openspec **無**「投影合併」指令，此合併為人工套用，可能與最終 `archive` 結果略有分歧——屬討論稿可接受範圍，`/spec-ship` 階段會以真實合併結果重生正式文件覆蓋。

**HTML 規格**：依 `/spec-retrofit` Phase 4「互動式 HTML 文件」硬性規格（單檔自包含、零外部資源、深色 sidebar TOC + scroll-spy、SHALL / MUST NOT badge、可摺疊 Scenario、刻意不做區塊），範本 `docs/auth-spec.html`。額外：

- `<title>` 設為「提案討論稿：<name>」，便於 `/docs` 列表辨識。
- 文件頂部加「提案背景」段，取 `proposal.md` 的 `## Why` 與 `## What Changes`。

**驗證**（同 retrofit Phase 4）：

1. requirement / scenario 數與合併後 spec 一致
2. grep 確認無外部資源引用（`src=`、`href=` 不得指向外部 URL）
3. HTML 標籤平衡

### 5. 顯示變更

```bash
git status --short openspec/changes/<name>/ docs/<name>.html
```

### 6. 詢問是否 commit

用 AskUserQuestion 問：「Commit spec？」
- **Commit**：進入 step 7
- **跳過**：提示「可後續手動 commit」，結束

### 7. Commit

從 proposal.md 的 `## Why` 截第一段壓成一句作為 commit body，組訊息（討論稿 HTML 一併進 commit 以便分享 / PR）：

```bash
git add openspec/changes/<name>/ docs/<name>.html
git commit -m "$(cat <<'EOF'
docs(spec): propose <name>

<Why 第一段壓成 1-2 句>
EOF
)"
git status
```

---

## Output

```
## Spec Finalized

**Change:** <name>
**Validate:** ✓
**Review:** <一句摘要>
**HTML:** docs/<name>.html（/docs/<name> 可瀏覽）
**Commit:** <hash> docs(spec): propose <name>

下一步：拿 `/docs/<name>` 與他人討論。
要調整 spec → 改 delta 後**重跑 `/spec-finalize`** 重生 HTML；確認無誤再 `/spec-build <name>` 實作。
```

---

## Guardrails

- artifacts 不存在時**不**自動 propose（請使用者回去走 `/opsx:explore`）
- Validate 失敗時不繼續到 review；改 spec 後從 step 2 重跑
- Review 若標出問題，先讓使用者裁示再 commit
- 討論稿 HTML 為**合併預覽**（人工套用 delta），非 archive 後的權威文件；正式 capability HTML 由 `/spec-ship` 重生
- 重跑 `/spec-finalize` 會覆蓋重生 `docs/<name>.html`（idempotent）
- HTML 零外部資源（no-outbound-internet 約束）
- commit 訊息來自 proposal.md，避免人工抄錯（CLAUDE.md：「Verify commit message items match the actual staged diff」）
- 用 HEREDOC 寫 commit message（CLAUDE.md 規定）
- 不自動 push
