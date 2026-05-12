---
name: "Spec: Ship"
description: 包裝 opsx:archive → 從 proposal.md 自動產生 final commit
category: Workflow
tags: [workflow, openspec, archive, commit]
---

完整「歸檔 → 產生 commit message → commit」流程。commit message 從 proposal.md 自動產生，避免人工抄錯。

**Input**: change name 或從 active change 推斷。

---

## Steps

### 1. Archive

呼叫 `opsx:archive` skill（用 Skill tool，skill name = `opsx:archive`）。

完成後拿到 archive 路徑：`openspec/changes/archive/YYYY-MM-DD-<name>/`

若 archive 失敗（例如目標路徑已存在）：停下，**不繼續 commit**。

### 2. Stage 變動

```bash
git add openspec/
git status --short
```

### 3. 產生 commit message

讀 `openspec/changes/archive/YYYY-MM-DD-<name>/proposal.md`，截：
- `## Why` 第一段（主要動機）
- 從 change name 去除動詞前綴作為 subject 主體
  - 例：`add-dashboard` → subject = `dashboard`
  - 例：`change-app-timezone-to-taipei` → subject = `app timezone to Taipei`

組成：

```
feat: <subject 主體>

<Why 第一段壓成 1-3 句>

ref: openspec/changes/archive/YYYY-MM-DD-<name>/
```

若 change 是 destructive（remove / delete）→ subject 改 `remove:`；若是純文件 → 改 `docs:`。

### 4. 確認 commit message

用 AskUserQuestion 顯示產生的 commit message，三個選項：
- **使用此 message commit**：進 step 5
- **編輯後 commit**：讓使用者貼修改版，再進 step 5
- **取消**：保留 archive 狀態，不 commit

### 5. Commit

```bash
git commit -m "$(cat <<'EOF'
<最終 commit message>
EOF
)"
git log -1 --oneline
git status
```

---

## Output

```
## Shipped

**Change:** <name>
**Archived to:** openspec/changes/archive/YYYY-MM-DD-<name>/
**Commit:** <hash> <subject>

Branch 已可推送 / 開 PR。
```

---

## Guardrails

- archive 失敗時**不**繼續 commit（避免半成品 commit）
- commit message 來源是 proposal.md，不要自由發揮
- 用 HEREDOC 寫 commit（CLAUDE.md 規定）
- **不**自動 push（CLAUDE.md：git credentials 可能未設定）
- 若 proposal.md 的 `## Why` 結構異常（無此標題、空段），降級為手動撰寫並警示使用者
