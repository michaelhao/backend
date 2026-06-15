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
