---
name: "Spec: Build"
description: 包裝 opsx:apply → pint → 測試 → /review → simplify（commit 由人工撰寫）
category: Workflow
tags: [workflow, openspec, build]
---

完整「實作 → 格式化 → 測試 → 審查 → 簡化掃描」流程。**不自動 commit**：實作 commit 顆粒度與訊息變數太多，留人工撰寫。

**Input**: change name 或從 active change 推斷（單一 active change 時自動選；多個時用 AskUserQuestion 詢問）。

---

## Steps

### 1. Apply tasks（每 task 後做 2、3）

呼叫 `opsx:apply` skill（用 Skill tool，skill name = `opsx:apply`）。

**重要**：在 opsx:apply 的迴圈中，每完成一個 task（勾選 `- [x]` 之後）必須執行 step 2、3 才進入下一個 task。若測試失敗就停在當前 task 修到綠才前進。

### 2. Pint 格式化（每 task 後）

```bash
docker compose exec backend-api vendor/bin/pint --dirty --format agent
```

CLAUDE.md：PHP 工具必須在 Docker 內跑。boost rules：modified PHP files 必須跑 pint。

### 3. 相關測試（每 task 後）

filter 名稱從剛改動的檔案推斷（例：改 `DashboardService.php` → `--filter=Dashboard`）。

```bash
docker compose exec backend-api php artisan test --compact --filter=<keyword>
```

失敗 → 停在當前 task 修正，重跑直到綠，再進下一個 task。

### 4. 全部 task 完成後：對照 spec 的 /review

呼叫 `review` skill 審查整個 branch 的 diff，重點確認實作 vs `openspec/changes/<name>/` 的 spec 是否一致。

若 review 發現實作偏離 spec：停下回報，討論要修實作還是更新 spec。

### 5. Simplify 掃描

呼叫 `simplify` skill（用 Skill tool，skill name = `simplify`）掃改動過的檔案，找 reuse 機會與冗餘。

### 6. 顯示狀態

```bash
git status --short
git diff --stat
```

### 7. 提示（不執行）commit

告訴使用者：所有 task 完成、測試/格式/審查/簡化都過了，可以 commit。**不執行** `git commit`。

可附上建議 commit subject 模板：`feat: <change-name 去前綴>`，但讓使用者自己決定顆粒度。

---

## Output

```
## Build Complete

**Change:** <name>
**Tasks:** N/N complete
**Pint:** ✓
**Tests:** ✓ all passing
**Review:** <實作 vs spec 一致性摘要>
**Simplify:** <發現的 reuse / 冗餘>

Ready to commit. 可以下手了。
完成後 `/spec-ship <name>` 歸檔。

🧭 explore → propose → finalize →（討論）→ build → ship → push/PR
   ▸ 你在「build」。下一步：commit 實作 → /spec-ship <name>。隨時 /spec-status 查看目前階段與下一步。
```

---

## Guardrails

- 每 task 完成後必跑 pint + 相關測試，不可 batch 到最後
- 測試失敗時停在當前 task，不進下一個
- **絕對不自動 commit**（人工撰寫；CLAUDE.md：commit 前要 verify 訊息對 diff）
- 若 /review 發現實作偏離 spec，停下回報；可能要更新 spec（回 `/spec-write`）或修實作
- Docker 指令統一用 `docker compose exec backend-api`（CLAUDE.md）
