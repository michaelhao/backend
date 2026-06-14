---
name: test-prompt-engineering
description: "Apply this skill whenever asked to write, generate, add, or refactor automated tests for this Laravel app (PHPUnit Feature/Unit tests under tests/). Use it to turn a vague request like 'add a test for X' into a structured test: clarify the behavior under test, gather real app context (routes, roles/permissions, factories), cover happy/failure/edge paths, write explicit assertions, and avoid hallucinated routes, columns, or business logic. Also use when designing test data or reviewing test coverage."
metadata:
  author: michaelhao
---

# 測試提示工程（Test Prompt Engineering）

把模糊的測試需求轉成**結構完整、可執行**的 PHPUnit 測試的流程與護欄。
收到「幫 X 加個測試」這類請求時，先補齊脈絡再下筆，不要憑空臆測路由 / 欄位 / 行為。

本 repo 測試全是 PHPUnit（`tests/Feature`、`tests/Unit`），沒有 Playwright。
程式碼層級的慣例（`RefreshDatabase`、model assertion、factory states）見
`laravel-best-practices/rules/testing.md`；本 skill 只談「怎麼把需求變成好測試」這一層，兩者搭配使用。

## 下筆前的五要素（把模糊需求結構化）

1. **明確測試目標** — 一個 test method 只驗證一條行為 / 規則，方法名講清楚在驗什麼。
2. **應用情境與流程** — 補齊實際路由、角色 / 權限、前置 seed。沿用既有
   `actingAsAdmin()` + `PermissionSeeder` 的登入與授權慣例，別自己另起一套。
3. **期望輸出結構** — Arrange → Act → Assert。Feature test 用 `route()` named route + 對應 HTTP 動詞
   （`$this->post(route('bills.store'), [...])`）。
4. **邊界與負向情境** — happy / failure / edge 都要涵蓋，不要只測成功路徑。命名沿用 repo 風格，
   例如 `test_..._is_rejected`、`test_..._is_accepted`、`test_..._fails_when_...`。
5. **驗證規則與斷言** — 斷言要具體：驗具體值與訊息，別只 `assertOk()` 就結束。
   常用：`assertSessionHasErrors('details.0.start_at')`、`assertDatabaseCount('bills', 0)`、
   `assertRedirect(route('bills.index'))`、帶訊息的 `assertSame($expected, $actual, '說明')`。

## 測試資料用 factory，不手刻

- 一律用 `Model::factory()` + 既有 state（見 testing.md 的 factory states）與 `PermissionSeeder` 產資料，
  不要手動 new model 或塞死值。需要共用情境就抽 helper（如 `makeShop()`），別在每個 test 複製貼上。

## 護欄（避開 AI 產測試的三大坑）

- **不幻覺** — 動手前先確認路由名、欄位、驗證鍵真的存在：docker 內 `php artisan route:list`、
  `database-schema` MCP、或直接讀 Controller / FormRequest / Service。不要對想像中的 API 寫測試。
- **不漏業務邏輯** — 對「真實行為」下斷言前，先讀 Service / Controller 確認它**實際**怎麼算、怎麼擋，
  再依實際行為寫期望值，不要照需求描述腦補。
- **資料安全** — 測試與提示一律用 factory 假資料，不放正式環境真實資料或機密。

## Before / After 提示對照

- ❌ 爛：「幫 login 頁面寫個測試。」（沒目標、沒情境、沒斷言）
- ✅ 好：「在 `tests/Feature/Auth/LoginTest.php` 加一個 test：未驗證 email 嘗試登入時，
  導回並帶 `email` 欄位錯誤、且未建立 session。用 `User::factory()->unverified()`，
  斷言 `assertSessionHasErrors('email')` 與 `assertGuest()`。」

## 收尾驗證

測試寫完在 docker 內只跑相關的：

```
docker compose exec backend-api php artisan test --compact --filter=<TestName>
```
