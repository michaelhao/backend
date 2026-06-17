# Superpowers Status 工具 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** 提供一個唯讀的「工作流狀態追蹤 + 下一步自動提示」機制,讓人與 agent 都不需靠記憶就知道目前在 superpowers 流程(brainstorming → writing-plans → execute → code-review → finish)的哪一階段、下一步該做什麼。

**Architecture:** 一支純推導的 shell 腳本作為唯一引擎(從 `docs/superpowers/plans/*.md` 的勾選狀態與分支是否併入 base 推導階段,不存狀態檔、不靠記憶);一個 slash command 作為「人手動問」的入口;一個 Stop hook 在每回合結束自動呼叫腳本顯示單行狀態(不在工作流時靜默)。引擎共用,對齊既有 `/spec-status` 的唯讀推導套路。

**Tech Stack:** bash、git、grep、Claude Code slash command(`.claude/commands/*.md`)、Claude Code hooks(`.claude/settings.json`)。

---

## 假設與限制(實作前先讀)

- **勾選狀態的準確度取決於執行方式**:`subagent-driven-development` 與 `executing-plans` 會在執行時勾掉 plan 檔的 `- [x]`;若採「inline 臨時執行」則不會勾。因此本工具的「執行中/完成」以 plan 勾選為準,inline 執行的舊計畫會被判為「待執行」(這是誠實反映產物,而非 bug)。要狀態準確,執行請走 subagent-driven / executing-plans。
- **唯讀**:腳本只做 `git` 讀取、`grep`、`ls`,不得改任何檔。
- **base 分支**:自動偵測 `master`,否則 `main`;都無則略過「已併入」判定。
- **多計畫**:取 `docs/superpowers/plans/` 中**最新**一份(mtime)。多功能並行時以最新為準;需要精準可再擴充。
- **hook schema 以 `update-config` skill 寫入為準**:本計畫給出設定形狀,實際寫入與驗證走 `update-config`,不手改 settings.json 結構靠記憶。

## File Structure

- **Create** `.claude/bin/superpowers-status.sh` — 唯讀推導引擎。兩種輸出:`--line`(單行,給 hook,無事靜默)、無參數(完整區塊,給人)。唯一真相來源。
- **Create** `.claude/commands/superpowers-status.md` — `/superpowers-status` slash command;呼叫腳本(完整模式)並原樣呈現。對齊 `.claude/commands/spec-status.md` 慣例。
- **Modify** `.claude/settings.json` — 新增 Stop hook,每回合跑 `--line`。經由 `update-config` skill 寫入。

---

## Task 1: 推導引擎腳本

**Files:**
- Create: `.claude/bin/superpowers-status.sh`

- [x] **Step 1: 寫腳本**

```bash
#!/usr/bin/env bash
# superpowers-status: 唯讀推導目前在 superpowers 工作流的哪一階段 + 下一步。
# 用法: superpowers-status.sh [--line]
#   --line   單行模式(給 Stop hook);不在進行中工作流時「靜默」(無輸出)
#   (無參數)  完整區塊(給人手動看)
set -uo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[ -z "$ROOT" ] && exit 0   # 不在 git repo → 靜默
cd "$ROOT" || exit 0

MODE="${1:-full}"
BRANCH="$(git branch --show-current 2>/dev/null || true)"
PLAN="$(ls -t docs/superpowers/plans/*.md 2>/dev/null | head -1 || true)"

# 偵測 base 分支(master 優先,否則 main)
if git rev-parse --verify -q master >/dev/null 2>&1; then
  BASE=master
elif git rev-parse --verify -q main >/dev/null 2>&1; then
  BASE=main
else
  BASE=""
fi

emit() { # $1=stage $2=next
  if [ "$MODE" = "--line" ]; then
    printf '📍 superpowers｜%s · 下一步:%s\n' "$1" "$2"
  else
    printf '## Superpowers Status\n\n'
    printf '**分支:** %s\n' "${BRANCH:-(none)}"
    printf '**計畫:** %s\n' "${PLAN:-(無)}"
    printf '**階段:** %s\n\n' "$1"
    printf '🧭 brainstorming → writing-plans → execute → code-review → finish\n'
    printf '   ▸ 下一步:%s\n' "$2"
  fi
}

# 無計畫檔
if [ -z "$PLAN" ]; then
  # 在 base 分支或無分支、又無計畫 → 視為不在工作流;line 模式靜默
  if [ "$MODE" = "--line" ] && { [ -z "$BRANCH" ] || [ "$BRANCH" = "$BASE" ]; }; then
    exit 0
  fi
  emit "發想/規劃前" "跑 /superpowers:brainstorming,接著 writing-plans 產出計畫"
  exit 0
fi

# 分支已併入 base → 完成;line 模式靜默
if [ -n "$BRANCH" ] && [ -n "$BASE" ] && git merge-base --is-ancestor "$BRANCH" "$BASE" 2>/dev/null; then
  [ "$MODE" = "--line" ] && exit 0
  emit "完成(已併入 ${BASE})" "—"
  exit 0
fi

DONE="$(grep -cE '^[[:space:]]*- \[x\]' "$PLAN" 2>/dev/null || true)"
TODO="$(grep -cE '^[[:space:]]*- \[ \]' "$PLAN" 2>/dev/null || true)"
DONE="${DONE:-0}"; TODO="${TODO:-0}"
TOTAL=$((DONE + TODO))

if [ "$TOTAL" -eq 0 ] || [ "$DONE" -eq 0 ]; then
  emit "已規劃,待執行" "選執行方式(subagent-driven / executing-plans)開工"
elif [ "$DONE" -lt "$TOTAL" ]; then
  emit "執行中 (${DONE}/${TOTAL})" "完成剩餘 task"
else
  emit "執行完成 (${DONE}/${TOTAL})" "跑 /code-review,再 finishing-a-development-branch 收尾"
fi
```

- [x] **Step 2: 驗證 — 完整模式(目前 feat/chat-system,計畫未勾選)**

Run: `bash .claude/bin/superpowers-status.sh`
Expected: 印出區塊,分支 `feat/chat-system`、計畫指向最新的 `docs/superpowers/plans/*.md`、階段「已規劃,待執行」(因現有計畫的 `- [x]` 未勾;符合上方「假設與限制」)。

- [x] **Step 3: 驗證 — 單行模式**

Run: `bash .claude/bin/superpowers-status.sh --line`
Expected: 一行 `📍 superpowers｜已規劃,待執行 · 下一步:選執行方式(subagent-driven / executing-plans)開工`。

- [x] **Step 4: 驗證 — base 分支靜默(line 模式)**

Run: `git stash -u >/dev/null 2>&1; git switch master 2>/dev/null && bash .claude/bin/superpowers-status.sh --line; echo "exit=$?"; git switch - >/dev/null 2>&1`
Expected: 無狀態行輸出、`exit=0`(在 base 分支且該處理為靜默)。
（若不想切分支,改用臨時 repo 驗證亦可;重點是 line 模式在 base/無計畫時不輸出。）

- [x] **Step 5: 驗證 — 勾選比例解析正確**

Run:
```bash
printf '%s\n' '- [x] a' '- [x] b' '- [x] c' > /tmp/_sp_plan_test.md
mkdir -p /tmp/_sp_test && cp /tmp/_sp_plan_test.md /tmp/_sp_test/2999-01-01-x.md
DONE=$(grep -cE '^[[:space:]]*- \[x\]' /tmp/_sp_test/2999-01-01-x.md); TODO=$(grep -cE '^[[:space:]]*- \[ \]' /tmp/_sp_test/2999-01-01-x.md)
echo "done=$DONE todo=$TODO"; rm -rf /tmp/_sp_test /tmp/_sp_plan_test.md
```
Expected: `done=2 todo=1`(確認 grep pattern 對 `- [x]` / `- [x]` 計數正確)。

- [x] **Step 6: Commit**

```bash
git add .claude/bin/superpowers-status.sh
git commit -m "$(printf 'feat(tooling): add read-only superpowers workflow status script\n\nCo-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>')"
```

---

## Task 2: /superpowers-status slash command

**Files:**
- Create: `.claude/commands/superpowers-status.md`

- [x] **Step 1: 寫 command(對齊 `.claude/commands/spec-status.md` 慣例)**

```markdown
---
name: "Superpowers: Status"
description: 顯示目前在 superpowers 工作流的哪一階段、下一步該做什麼(唯讀,從計畫產物推導,不靠記憶)
category: Workflow
tags: [workflow, superpowers, status]
---

唯讀診斷:回答「我在 superpowers 工作流哪一階段?下一步該做什麼?」。**不做任何修改**(不 edit、不 commit)。

工作流鏈:

```
brainstorming → writing-plans → execute → code-review → finish
```

## Steps

1. 執行專案腳本:`bash .claude/bin/superpowers-status.sh`
2. 將其輸出**原樣**呈現給使用者。
3. 若輸出為空,回報:「目前不在進行中的 superpowers 工作流(在 base 分支或尚無計畫)。」

## Guardrails

- 全程唯讀:只允許執行 `superpowers-status.sh`(內部僅 git 讀取 + grep + ls)。不得 edit、commit、跑測試或改任何檔。
- 階段判定以 `docs/superpowers/plans/*.md` 的勾選狀態與分支是否併入 base 為準,不依賴 git 寫入或對話記憶。
- 勾選準確度依賴執行時有勾 checkbox(subagent-driven / executing-plans 會);inline 執行的舊計畫會顯示「待執行」。
```

- [x] **Step 2: 驗證**

Run: 在 Claude Code 輸入 `/superpowers-status`
Expected: 出現 Task 1 完整模式的同一狀態區塊。

- [x] **Step 3: Commit**

```bash
git add .claude/commands/superpowers-status.md
git commit -m "$(printf 'feat(tooling): add /superpowers-status slash command\n\nCo-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>')"
```

---

## Task 3: Stop hook(自動提示)

**Files:**
- Modify: `.claude/settings.json`

- [x] **Step 1: 用 update-config skill 加入 Stop hook**

呼叫 `update-config` skill,將下列 hook 寫入 `.claude/settings.json`(與既有 `enabledPlugins` 並存):

```json
{
  "hooks": {
    "Stop": [
      {
        "hooks": [
          { "type": "command", "command": "bash .claude/bin/superpowers-status.sh --line" }
        ]
      }
    ]
  }
}
```
(以 `bash <script>` 呼叫,避免依賴執行位元;確切 schema 以 update-config 寫入/驗證為準。)

- [x] **Step 2: 驗證 — 工作流中會自動顯示**

在 `feat/chat-system`(有計畫)隨意送一則訊息給 Claude Code,回合結束後應在輸出尾端看到單行:
`📍 superpowers｜已規劃,待執行 · 下一步:…`

- [x] **Step 3: 驗證 — 非工作流時靜默**

切到 `master`(或無計畫情境)再送一則訊息,回合結束**不應**出現狀態行(line 模式靜默)。

- [x] **Step 4: Commit**

```bash
git add .claude/settings.json
git commit -m "$(printf 'feat(tooling): surface superpowers status via Stop hook\n\nCo-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>')"
```

---

## Self-Review

**Spec coverage:**
- 狀態追蹤(唯讀推導,不靠記憶):Task 1 引擎 ✓
- 手動入口:Task 2 slash command ✓
- 自動提示(Stop hook):Task 3 ✓
- 不在工作流時靜默(避免洗版):Task 1 的 `--line` 靜默分支 + Task 3 Step 3 驗證 ✓
- 對齊既有 spec-status 唯讀套路:Task 2 front-matter/guardrails ✓

**Placeholder scan:** 無 TODO/TBD;每個 step 皆有實際腳本/設定/指令與預期輸出。

**一致性:** 腳本名 `.claude/bin/superpowers-status.sh`、`--line` 旗標、command `/superpowers-status`、hook 呼叫 `bash .claude/bin/superpowers-status.sh --line` 在三個 task 間一致;階段字串(發想/規劃前、已規劃待執行、執行中、執行完成、完成)在腳本與 command 描述一致。

**已知限制(已於頂部載明):** inline 執行不勾 checkbox → 舊計畫顯示「待執行」;多計畫取最新。屬刻意取捨,非缺漏。

---

## Execution Handoff

兩種執行方式:
1. **Subagent-Driven** — 每 task 一個 subagent + 兩階段 review。
2. **Inline Execution** — 本 session 用 executing-plans 批次執行,設檢查點。

本工具僅 3 個小檔、無應用邏輯,建議 **Inline Execution**;其中 Task 3 需呼叫 `update-config` skill 寫 hook。
