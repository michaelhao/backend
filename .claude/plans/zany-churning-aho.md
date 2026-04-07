# 建立 `_context` 資料夾

## Context
使用者希望在專案根目錄建立一個 `_context` 資料夾，並確保它不會被推送到 GitHub。

## 步驟

1. **建立 `_context/` 資料夾**：在專案根目錄建立，並放入一個 `.gitkeep` 檔案（讓 Git 能感知資料夾結構，但內容不追蹤）。
2. **更新 `.gitignore`**：加入 `/_context` 規則，排除該資料夾的所有內容。

## 修改檔案
- `.gitignore` — 新增 `/_context` 一行
- `_context/.gitkeep` — 新建空檔（不需要，因為整個資料夾都會被忽略，只需建立資料夾即可）

## 驗證
- `git status` 確認 `_context/` 不會出現在未追蹤檔案中
