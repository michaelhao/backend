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

### 4. 顯示變更

```bash
git status --short openspec/changes/<name>/
```

### 5. 詢問是否 commit

用 AskUserQuestion 問：「Commit spec？」
- **Commit**：進入 step 6
- **跳過**：提示「可後續手動 commit」，結束

### 6. Commit

從 proposal.md 的 `## Why` 截第一段壓成一句作為 commit body，組訊息：

```bash
git add openspec/changes/<name>/
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
**Commit:** <hash> docs(spec): propose <name>

下一步：`/spec-build <name>` 開始實作
```

---

## Guardrails

- artifacts 不存在時**不**自動 propose（請使用者回去走 `/opsx:explore`）
- Validate 失敗時不繼續到 review；改 spec 後從 step 2 重跑
- Review 若標出問題，先讓使用者裁示再 commit
- commit 訊息來自 proposal.md，避免人工抄錯（CLAUDE.md：「Verify commit message items match the actual staged diff」）
- 用 HEREDOC 寫 commit message（CLAUDE.md 規定）
- 不自動 push
