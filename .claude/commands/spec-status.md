---
name: "Spec: Status"
description: 顯示目前 change 在工作流的哪一階段、下一步該跑哪個指令(唯讀,不需靠記憶)
category: Workflow
tags: [workflow, openspec, spec, status]
---

唯讀診斷:回答「我在工作流哪一階段?下一步該跑什麼指令?」。**不做任何修改**(不 edit、不 commit、不 archive)。

專案工作流鏈:

```
explore(可選) → propose → finalize →(討論)→ build → ship → push/PR
```

**Input**: change name(可選)。省略時自動推斷:單一 active change 直接用;多個時詢問。

---

## Steps

### 1. 找出 active change

```bash
openspec list --json
```

- `changes: []`(空）→ 目前無進行中 change。輸出:
  - 「目前無 active change。」
  - 下一步:`/opsx:explore`(想先想清楚)或 `/opsx:propose`(直接開提案)。
  - 補一句:若你剛 `/spec-ship` 完,該分支已可 `git push` / 開 PR。
  - **結束**。
- 多個 → 列出,用 AskUserQuestion 讓使用者選一個(或逐一報狀態)。
- 一個 → 用該 name(若有傳入 name 以傳入為準)。

### 2. 取得狀態訊號

```bash
openspec status --change "<name>" --json
openspec instructions apply --change "<name>" --json
```

解析:
- artifacts 完成度(`applyRequires` 是否都 `done`;或 `state` 是否為 `blocked`)
- tasks 進度(`progress.total` / `progress.complete`)
- `state`：`blocked`(artifacts 未齊)/ `ready`(可實作)/ `all_done`(tasks 全完成)

```bash
ls docs/<name>.html 2>/dev/null   # 討論稿是否存在 = finalize 是否已跑
```

### 3. 判定階段並決定下一步

| 條件 | 階段 | 下一步指令 |
|---|---|---|
| `state=blocked` / artifacts 未齊 | **提案中** | 補 artifacts:`/opsx:propose <name>`（必要時先 `grill-me` 壓測再修） |
| artifacts 齊 + **無** `docs/<name>.html` | **spec 待收尾** | `/spec-finalize <name>` |
| `docs/<name>.html` 存在 + tasks 未全完成 | **討論 / 待實作** | 先開 `/docs/<name>` 與人討論;確認後 `/spec-build <name>`（要調 spec 就改 delta 重跑 `/spec-finalize`） |
| `state=all_done`（tasks 全完成） | **實作完成、待歸檔** | `/spec-ship <name>` |

### 4. 輸出

顯示:目前 change 名、tasks 進度(N/M)、所在階段、下一步指令(明確),並畫出進度列標出位置:

```
## Spec Status

**Change:** <name>
**Tasks:** <complete>/<total>
**階段:** <stage>

🧭 explore → propose → finalize →(討論）→ build → ship → push/PR
   ▸ 你在「<stage>」。下一步:<下一步指令>。

下一步:<一句具體說明,含確切指令>
```

---

## Guardrails

- **全程唯讀**:只允許 `openspec list/status/instructions` 與 `ls`;不得 edit、commit、archive、跑測試或改任何檔。
- 階段判定以 `openspec` JSON + `docs/<name>.html` 檔案訊號為準,不依賴 git 或對話記憶。
- 下一步一律指向**專案鏈**(`spec-finalize → spec-build → spec-ship`),不沿用 `opsx:propose` 內建指向 `/opsx:apply` 的提示。
- 多個 active change 時先問清楚再報,不臆測。
