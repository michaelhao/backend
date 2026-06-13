# tasks — retrofit-admin-layout-spec

本 change 為文件回填：程式實作已於 commit `f0d8bdc`（refactor 移除 sidebar 死連結「文章管理」）、`5ea8aa7`（fix session 計時器色彩 class 不一致）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於上列 commits）

- [x] 1.1 移除 sidebar `Post.index`「文章管理」佔位連結（權限不存在、href=#，死碼）（f0d8bdc）
- [x] 1.2 `admin.js` 計時器 `text-gray-400` → `text-slate-400`，消除 no-op remove 死碼（5ea8aa7）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/admin-layout/spec.md（MODIFIED 視覺主題 8 選單 + ADDED 外殼/導覽/頂部列/計時器/無角色頁/訪客版型/MUST NOT）
- [x] 2.4 刪除舊版 `spec/2-backendLayout.md`
- [x] 2.5 `openspec validate retrofit-admin-layout-spec` 通過後 archive，合併至 `openspec/specs/admin-layout/spec.md`

## 3. HTML 文件

- [x] 3.1 從 spec.md 產生 `docs/admin-layout-spec.html`（單檔自包含、零外部資源，範本 `docs/auth-spec.html`）
- [x] 3.2 驗證 requirement/scenario 數量一致、無外部資源引用、HTML 標籤平衡
- [x] 3.3 全套件測試確認無回歸
