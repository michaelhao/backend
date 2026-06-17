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
