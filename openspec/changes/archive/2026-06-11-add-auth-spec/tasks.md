# tasks — add-auth-spec

本 change 為文件回填：程式實作已於 commit `4adf510`（refactor(auth): 登入流程設計 review 改善）與 `9339bf4`（fix(validation): 移除 Password::uncompromised() 的 HIBP 外部檢查）完成，對應任務直接標記完成。

## 1. 程式實作（已完成於 4adf510 / 9339bf4）

- [x] 1.1 AuthService 移除 `request()->session()` 依賴，session 操作移回 LoginController（4adf510）
- [x] 1.2 新增 `LoginRequest` 取代 inline validate（4adf510）
- [x] 1.3 ThrottleLogin：email key 小寫 + transliterate 正規化、登入成功清除計數（4adf510）
- [x] 1.4 密碼重設成功後刪除該使用者其他 sessions（`UserRepository::deleteSessionsByUserId`）並發 `PasswordReset` 事件（4adf510）
- [x] 1.5 移除三處 `Password::uncompromised()`（ResetPasswordRequest / StoreUserRequest / UpdateUserRequest）與測試中的 HIBP `Http::fake`（9339bf4）
- [x] 1.6 新增 `tests/Feature/Auth/LoginTest.php`（9 案例）、`PasswordResetTest` 補 sessions 失效與事件案例；全套件 211 passed（4adf510 / 9339bf4）

## 2. Spec 文件

- [x] 2.1 撰寫 proposal.md（文件回填動機與範圍）
- [x] 2.2 撰寫 design.md（retroactive spec 的關鍵設計決策記錄）
- [x] 2.3 撰寫 specs/auth/spec.md（13 項 Requirement + Scenario，對應既有測試）
- [x] 2.4 刪除舊版 `spec/1-login.md` 與 `spec/14-login-security-review.md`
- [x] 2.5 `openspec validate add-auth-spec` 通過後 archive，合併至 `openspec/specs/auth/spec.md`
