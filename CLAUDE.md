# Claude Code 設定

## Docker Environment
- All PHP/artisan/composer/test commands MUST run inside the Docker container, NOT on the host.
- Docker applies only to the local dev environment.
- Use `docker compose exec backend-api <cmd>` (service name) or `docker exec wsl-backend <cmd>` (container name) for all PHP tooling.

## Git & CI
- Git credentials and `gh` CLI may not be configured. Do not attempt `git push` or `gh pr create` without confirming availability first.

## Language
- Always respond in the user's language (Chinese). Never switch languages mid-response.
- Code, file paths, and technical identifiers stay in their original form — these don't count as switching.

## Commit Discipline
- Verify commit message items match the actual staged diff before committing (no phantom items).

## Workflow
- When in plan mode and user says '更新 spec' or similar, update the spec file directly — do not write to a separate plan file.
- Before implementing, confirm the direction of relationships/ownership (e.g., which model 'manages' which) to avoid reversed designs.
- Do not create additional memory files to patch rules; edit CLAUDE.md directly.

### Superpowers 工作流銜接
- 跑 superpowers 流程 skill 時,以「該 skill 的終點狀態」為準;plan mode 的 ExitPlanMode 核准 **不等於** 可以開始寫 code。
- `brainstorming` 的終點是呼叫 `writing-plans`(產出實作計畫),**不是**直接實作。
- **`brainstorming` 定案 spec 後、進 `writing-plans` 前,新增一步:產生互動式 HTML spec 供 PM/前端討論。**
  - 路徑 `docs/<topic>-spec.html`(沿用既有 `docs/*-spec.html` 系列命名)。
  - 硬規格沿用 `/spec-retrofit` Phase 4「互動式 HTML 文件」、範本 `docs/auth-spec.html`:單檔自包含、**零外部資源**(no-outbound-internet)、深色 sidebar TOC + scroll-spy、SHALL / MUST NOT badge、可摺疊 Scenario、「刻意不做」區塊。
  - 目的:與 PM / 前端討論;若討論後修訂 spec,**重跑此步重生 HTML**(idempotent)。確認無誤後才進 `writing-plans`。
- 動任何實作工具(Edit/Write 原始碼、`artisan make:*`)之前,必須先有 `writing-plans` 產出的計畫,且已明確選定執行方式(subagent-driven / executing-plans)。動工前自我檢核:「計畫有了嗎?執行方式選了嗎?」否則退回補齊。
- 走 superpowers 整套流程時,優先**不要同時開 plan mode**,讓 skill 自己驅動 brainstorming → writing-plans → 執行;plan mode 留給不套 skill 的單純探查/小修。

## Coding Behavior Guidelines

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

### 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

## 前端 CSS 設計系統規範 (Tailwind v4)

本專案採 Tailwind v4(`@tailwindcss/vite`),**沒有 `tailwind.config.js`**。新增/修改前端樣式時遵循以下規範,避免 utility class 到處重複、改一個值要全站手改。

1. **設計 token 寫在 `@theme`**:顏色、字型等 token 一律定義在 `resources/css/app.css` 的 `@theme` 區塊(v4 取代 `tailwind.config.js` 的方式)。

2. **顏色走 token,不寫死**:blade 用 `bg-primary`、`bg-overlay` 等語意色,**禁止**直接寫 `bg-black/50`、`bg-blue-600` 這類品牌/語意色。現有 token:`--color-overlay`、`--color-primary`、`--color-primary-hover`。需要新語意色先在 `@theme` 加 token。

3. **重複樣式抽元件類別**:同一串 utility 重複 **3 次以上** 才抽(遵守 Simplicity First,單次使用不抽)。抽到 `resources/css/components/<群組>.css`,用 `@layer components` + `@apply`,並在 `app.css` `@import`。維持純 CSS `@apply` 作法,**不**改用 Blade 元件(與既有 `.form-control` 先例一致)。

4. **造新類別前先查現有的可重用清單**:
   - 表單:`.form-control` / `.form-label` / `.form-error`
   - Modal:`.modal-overlay` / `.modal-panel` / `.modal-actions`
   - 按鈕:`.btn-primary`(大尺寸用 `btn-primary px-6`)/ `.btn-cancel`
   - 版面:`.card` / `.table` / `.table-head` / `.page-title`
   - Flash:`.flash` + `.flash-success` / `.flash-error`

5. **JS 契約類別不可包進元件類**:被 JS 切換或查詢的 class/id 必須以字面 utility 留在 element 上。
   - `hidden`(modal 顯示/隱藏靠 `classList` 切換)→ 寫 `class="modal-overlay hidden"`,**不可**把 `hidden` 放進 `.modal-overlay`。
   - `.flash-message`、`.flash-area`、`#delete-modal*`、`.delete-btn` 等 hook 維持原樣。
   - flash 動態樣式產生在 `resources/js/utils/flash.js`,改 flash 樣式時 blade 與此 JS 要同步。

6. **改色驗證**:改 token 後跑 `docker compose exec backend-api npm run build`,確認全站同步生效。

---

## Migrate rules

- Replace timestamps with created_at and updated_at.
- Use created_at and updated_at instead of timestamps.
- Set the format to datetime

## Layered Architecture Standards

- Model Layer:

Responsibilities: Define Table Schemas, Relationships, Casts, Scopes, and Accessors/Mutators only.

Prohibitions: Must not contain complex query logic or business operations.

- Repository Layer:

Responsibilities: Encapsulate all Eloquent (ORM) queries.

Naming Convention: Use verb-prefixes, such as getById(), getActiveUsers(), or createWithProfile().

Goal: Decouple the database implementation from the Service layer, ensuring the Service layer remains agnostic of underlying data sources.

- Service Layer:

Responsibilities: Handle Business Logic. This includes payment gateway integration, permission checks, orchestrating multiple Repositories, and triggering notifications.

Principles: Service methods should return DTOs (Data Transfer Objects) or processed data, rather than raw Request objects.


- Controller Layer:

Responsibilities: Strictly limited to Request Validation, invoking Services, and returning Responses (Inertia, JSON, or Views).

Goal: Maintain "Skinny Controllers" by keeping the lines of code as concise as possible.

===

## SOLID & Clean Architecture Principles

To maintain a maintainable and scalable codebase, follow these specific implementations of SOLID and Clean Architecture:

- **S (Single Responsibility):** - A class should have one reason to change. 
    - If a Service is handling both "Data Processing" and "Third-party API Communication", split it.
- **O (Open/Closed):** - Use Interfaces (Contracts) for external integrations (e.g., Payment, Storage) to allow swapping implementations without touching business logic.
- **L (Liskov Substitution):** - Ensure subclasses or interface implementations do not break the application's behavior when swapped.
- **I (Interface Segregation):** - Prefer small, specific interfaces over large, "fat" ones.
- **D (Dependency Inversion):** - **High-level modules (Services) must not depend on low-level modules (Eloquent Models/External SDKs).**
    - Always type-hint Interfaces in constructors, not concrete implementations.

### Dependency Flow & Boundaries
- **Inner Circle (Entities/Business Logic):** Service Layer. Should have zero knowledge of HTTP requests or Database specificities.
- **Outer Circle (Infrastructure/Delivery):** Controllers, Repositories, Migrations, and Third-party SDKs.
- **Rule:** Dependencies must only point **inwards**. Services should interact with Repositories via Interfaces.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
