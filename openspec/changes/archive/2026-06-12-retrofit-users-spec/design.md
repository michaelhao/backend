# design — retrofit-users-spec

## Context

這是一份 **retroactive spec**（文件回填）：實作已完成並 commit（`898c597`、`4708b1a`、`f65d773`、`30c45d8`），本 change 只產出 spec 文件、不寫程式。以下記錄已實作架構背後的關鍵決策，作為 spec Requirements 的依據。

## Goals / Non-Goals

**Goals:**
- 一份 `user-management` capability spec 完整描述使用者 CRUD、密碼政策、自我保護與刪除參照檢查的可觀察行為
- 每個 Scenario 對應一個既有測試案例（UserCrudTest 26 案例），spec 可被測試驗證
- 記錄「刻意不做」的設計（無自助註冊、無 email 驗證、歷史稽核參照不擋刪除）與其依據，避免後人誤判為缺漏

**Non-Goals:**
- 不改任何程式碼、不新增測試
- 不規範登入/登出/密碼重設行為（屬 auth 範疇，`openspec/specs/auth/spec.md` 已涵蓋）；本 spec 僅規範管理員對使用者帳號的 CRUD
- 不規範角色與權限的定義與指派規則（屬 role-management 範疇），僅規範使用者表單的 `role_id` 驗證與自我角色保護

## Decisions

1. **帳號僅由管理員建立，無自助註冊與 email 驗證**：內部後台、使用者即內部人員，由持 `User.create` 權限者開帳號即可；無 `/register` 路由，`MustVerifyEmail` 未啟用（spec/4 原始範圍即如此，刻意維持）。
2. **密碼政策集中於 `Password::defaults()`**：min:12 + 大小寫 + 數字 + 符號，定義於 `AppServiceProvider::boot()`，Store/Update/ResetPassword 三個 FormRequest 共用（spec/16 P1-2 原案，2026-06-12 review 補完成，commit `f65d773`）。**不使用 `uncompromised()`（HIBP）**——正式環境無對外網際網路連線，外部 API 呼叫必定失敗。
3. **自我保護規則：不可刪除自己、不可修改自己的角色**：防止管理員誤操作把自己鎖出系統或意外降權（spec/16 P1-1）；name/email/password 不受影響仍可自改。規則位於 `UserService`（拋 `UserOperationException`），Controller 僅轉譯回應——分層比照 role-management 基準（2026-06-12 review，commit `898c597`）。
4. **不採 route model binding**：保留 `{id}` + `findUserById()` + redirect-with-flash 的 UX（源自 spec/7）。edit/update 找不到導回列表 + error flash；destroy 為 axios 請求，找不到回 404 JSON（原 422 語意為驗證錯誤，2026-06-12 review 修正，commit `4708b1a`）。
5. **刪除走 axios + 確認 modal 回 JSON**：spec/4 原設計為 form + confirm + redirect flash，後續比照 spec/10 的 axios 模式改版，免整頁 reload；成功後前端移除該列。
6. **刪除時清除該使用者的 database sessions**：複用 `UserRepository::deleteSessionsByUserId()`（AuthService 重設密碼亦用）；被刪者的既有 session 即刻清除而非等到下次請求才失效（2026-06-12 review，commit `4708b1a`）。僅適用 `SESSION_DRIVER=database`。
7. **刪除參照檢查只擋 `bills.shop_sales_id`（帳單業務），不擋歷史稽核參照**：業務指派會在帳單列表/篩選持續顯示與使用，懸掛參照會造成功能異常；`bills.creator_id` 與帳單狀態紀錄的操作者屬歷史稽核資料，擋下會讓多數使用者永遠無法刪除，刻意不擋（2026-06-12 review 裁示）。
8. **更新時密碼留空＝不修改**：管理員編輯使用者基本資料時不需重設密碼；空字串由 `UserService::updateUser()` 剝除後才進 Repository（spec/4 設計）。

## Risks / Trade-offs

- [Spec 與程式碼漂移] → 每個 Scenario 綁定既有測試；行為變更時測試先失敗，提醒同步更新 spec
- [被刪使用者若為 bills 歷史 creator/操作者，名稱顯示為懸掛參照] → 接受：稽核紀錄以 id 留存，顯示層以 nullsafe（`?->`）處理
- [sessions 清理僅適用 database driver] → 接受：專案固定使用 `SESSION_DRIVER=database`，Repository PHPDoc 已註明
- [自我保護僅防誤操作，不防多管理員互相降權] → 接受：威脅模型內能登入後台者即受信任的內部人員，與 role-management 的自我提權取捨一致
