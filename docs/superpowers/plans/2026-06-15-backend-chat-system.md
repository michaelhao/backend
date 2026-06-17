# 後台一對一即時聊天系統 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在既有 Laravel 13 後台新增「一對一即時文字聊天」,讓所有登入後台的使用者可彼此私訊,含未讀紅點、線上狀態與輸入中提示。

**Architecture:** 沿用 Controller→Service→Repository→Model 分層;資料用 `chat_conversations`(canonical pair)+ `chat_messages` + `chat_conversation_participants`(`last_read_at` 追蹤未讀);即時更新走自架 Laravel Reverb(WebSocket),`MessageSent` 廣播到接收者私有頻道 `chat.user.{id}`,輸入中用 `chat.conversation.{id}` 的 whisper,線上狀態用 presence channel `chat.online`。

**Tech Stack:** Laravel 13 / PHP 8.4(容器)、MySQL、Blade + 原生 JS、Tailwind v4 + Vite、Laravel Reverb、laravel-echo + pusher-js、PHPUnit 12。

---

## 專案慣例(每個任務都要遵守)

- **所有 PHP/artisan/composer/npm 指令一律在 docker 容器內執行**:`docker exec wsl-backend <cmd>`(或 `docker compose exec backend-api <cmd>`)。不要在 host 直接跑。
- **Migration**:匿名 class;時間欄位用 `dateTime('created_at')->nullable()` + `dateTime('updated_at')->nullable()`(不要 `timestamps()`);外鍵參照欄位用 `unsignedInteger`,**不加 DB 外鍵約束**(比照 `role_has_permissions`、`users.role_id`)。
- **Model**:用 `#[Fillable([...])]` attribute + `protected function casts(): array`(比照 `app/Models/User.php`)。表名由 Laravel 自動複數化,**不需** `protected $table`。
- **Service**:建構子屬性提升注入 Repository;回傳處理過的 array/model;違反業務規則拋 `ChatOperationException`(比照 `UserOperationException` 與 `UserService`)。
- **Controller**:薄;每個 method 掛 `#[RequiresPermission('Chat.index')]`;catch `ChatOperationException` 後回 JSON。
- **Form Request**:`authorize(): true` + `rules()` 陣列表示法(比照 `StoreUserRequest`)。
- **不要動 `AppServiceProvider::boot()`** 放全域設定(專案規則);未讀 badge 由前端 AJAX + Echo 處理。
- **完成 PHP 變更後**跑 `docker exec wsl-backend vendor/bin/pint --dirty --format agent`。
- **Commit**:依使用者 git 紀律(需使用者同意才 commit);commit message 結尾加 `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`,且訊息項目須與實際 staged diff 相符。下方各任務的 commit step 為建議檢查點。
- **跑測試**:`docker exec wsl-backend php artisan test --compact --filter=<Name>`;測試 DB 為 `my_database_testing`(已存在,`LazilyRefreshDatabase` 會自動 migrate)。

## File Structure

**新增(後端)**
- `database/migrations/*_create_chat_conversations_table.php` — conversations schema
- `database/migrations/*_create_chat_messages_table.php` — messages schema
- `database/migrations/*_create_chat_conversation_participants_table.php` — participants schema
- `app/Models/ChatConversation.php` / `ChatMessage.php` / `ChatConversationParticipant.php` — Eloquent models
- `database/factories/ChatConversationFactory.php` / `ChatMessageFactory.php` / `ChatConversationParticipantFactory.php`
- `app/Repositories/ChatConversationRepository.php` / `ChatMessageRepository.php` / `ChatConversationParticipantRepository.php`
- `app/Exceptions/ChatOperationException.php`
- `app/Services/ChatService.php`
- `app/Events/MessageSent.php`
- `app/Http/Requests/StartConversationRequest.php` / `SendMessageRequest.php`
- `app/Http/Controllers/ChatController.php`
- `routes/channels.php`(由 install:broadcasting 產生後覆寫)

**修改(後端)**
- `routes/web.php` — 在 `permission` group 內加 chat 路由
- `database/seeders/PermissionSeeder.php` — 加 `Chat` 模組
- `bootstrap/app.php` — 確認 `withBroadcasting(routes/channels.php)` 已註冊
- `.env` / `.env.example` — Reverb 設定
- `phpunit.xml` — channel 授權測試所需 env(若 null driver 無法簽章時)
- `composer.json` / `package.json` — 由套件安裝指令自動更新

**新增(前端)**
- `resources/views/admin/chats/index.blade.php` — 雙欄聊天頁
- `resources/js/chats/index.js` — 聊天頁邏輯(列表/切換/送訊息/typing/presence)

**修改(前端)**
- `resources/js/bootstrap.js` — Echo 初始化 + 全站未讀 badge
- `resources/views/layouts/admin.blade.php` — `meta[name=user-id]`、側欄聊天入口 + badge
- `vite.config.js` — 加 `resources/js/chats/index.js` entry

**新增(測試)**
- `tests/Feature/Chat/ChatRepositoryTest.php`
- `tests/Feature/Chat/ChatServiceTest.php`
- `tests/Feature/Chat/ChatConversationTest.php`
- `tests/Feature/Chat/ChatMessageTest.php`
- `tests/Feature/Chat/ChatUnreadTest.php`
- `tests/Feature/Chat/ChatChannelAuthTest.php`

**Docker**
- `/home/michaelhao/workspace/docker-compose.yml` — 新增 `reverb` service

---

## Task 1: 資料庫 Migration

**Files:**
- Create: `database/migrations/2026_06_15_000001_create_chat_conversations_table.php`
- Create: `database/migrations/2026_06_15_000002_create_chat_messages_table.php`
- Create: `database/migrations/2026_06_15_000003_create_chat_conversation_participants_table.php`

> 用 artisan 產生以取得正確時間戳,再覆寫內容。三檔執行順序需為 conversations → messages → participants(時間戳遞增即可)。

- [ ] **Step 1: 產生三個 migration 骨架**

```bash
docker exec wsl-backend php artisan make:migration create_chat_conversations_table --no-interaction
docker exec wsl-backend php artisan make:migration create_chat_messages_table --no-interaction
docker exec wsl-backend php artisan make:migration create_chat_conversation_participants_table --no-interaction
```

- [ ] **Step 2: 寫 conversations migration**(覆寫剛產生的 `*_create_chat_conversations_table.php`)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_one_id'); // 規範：較小的 user id
            $table->unsignedInteger('user_two_id'); // 規範：較大的 user id
            $table->unsignedInteger('last_message_id')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_one_id', 'user_two_id'], 'chat_conv_pair_unique');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
```

- [ ] **Step 3: 寫 messages migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('sender_id');
            $table->text('body');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['conversation_id', 'id'], 'chat_msg_conv_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
```

- [ ] **Step 4: 寫 participants migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('last_read_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['conversation_id', 'user_id'], 'chat_participant_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversation_participants');
    }
};
```

- [ ] **Step 5: 跑 migration 驗證**

```bash
docker exec wsl-backend php artisan migrate
```
Expected: 三個 migration 都 `DONE`,無錯誤。

- [ ] **Step 6: Commit**

```bash
git add database/migrations
git commit -m "feat(chat): add chat conversations, messages, participants migrations"
```

---

## Task 2: Models + Factories

**Files:**
- Create: `app/Models/ChatConversation.php`, `app/Models/ChatMessage.php`, `app/Models/ChatConversationParticipant.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/ChatConversationFactory.php`, `ChatMessageFactory.php`, `ChatConversationParticipantFactory.php`
- Test: `tests/Feature/Chat/ChatRepositoryTest.php`(本任務先建模型,Task 3 補測試;此處先寫一個模型關聯 smoke test)

- [ ] **Step 1: 產生 model + factory 骨架**

```bash
docker exec wsl-backend php artisan make:model ChatConversation -f --no-interaction
docker exec wsl-backend php artisan make:model ChatMessage -f --no-interaction
docker exec wsl-backend php artisan make:model ChatConversationParticipant -f --no-interaction
```

- [ ] **Step 2: 寫 `app/Models/ChatConversation.php`**

```php
<?php

namespace App\Models;

use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_one_id', 'user_two_id', 'last_message_id', 'last_message_at'])]
class ChatConversation extends Model
{
    /** @use HasFactory<ChatConversationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_one_id' => 'integer',
            'user_two_id' => 'integer',
            'last_message_id' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatConversationParticipant::class, 'conversation_id');
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function otherUserId(int $userId): int
    {
        return $this->user_one_id === $userId ? $this->user_two_id : $this->user_one_id;
    }
}
```

- [ ] **Step 3: 寫 `app/Models/ChatMessage.php`**

```php
<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'sender_id', 'body'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'sender_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
```

- [ ] **Step 4: 寫 `app/Models/ChatConversationParticipant.php`**

```php
<?php

namespace App\Models;

use Database\Factories\ChatConversationParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'user_id', 'last_read_at'])]
class ChatConversationParticipant extends Model
{
    /** @use HasFactory<ChatConversationParticipantFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'user_id' => 'integer',
            'last_read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

- [ ] **Step 5: 在 `app/Models/User.php` 加關聯**

在 `casts()` method 後面、class 結尾前加入(並在頂部 `use` 區加 `use Illuminate\Database\Eloquent\Relations\HasMany;`):

```php
    public function chatParticipants(): HasMany
    {
        return $this->hasMany(ChatConversationParticipant::class, 'user_id');
    }
```

- [ ] **Step 6: 寫三個 factory**

`database/factories/ChatConversationFactory.php` 的 `definition()`:
```php
    public function definition(): array
    {
        return [
            'user_one_id' => User::factory(),
            'user_two_id' => User::factory(),
        ];
    }
```
頂部加 `use App\Models\User;`。

`database/factories/ChatMessageFactory.php` 的 `definition()`:
```php
    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
```
頂部加 `use App\Models\ChatConversation;` 與 `use App\Models\User;`。

`database/factories/ChatConversationParticipantFactory.php` 的 `definition()`:
```php
    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'user_id' => User::factory(),
            'last_read_at' => null,
        ];
    }
```
頂部加 `use App\Models\ChatConversation;` 與 `use App\Models\User;`。

- [ ] **Step 7: 寫模型關聯 smoke test**(`tests/Feature/Chat/ChatRepositoryTest.php` 先放這一個)

```php
<?php

namespace Tests\Feature\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_conversation_has_messages_and_participants_relations(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = ChatConversation::factory()->create([
            'user_one_id' => $a->id,
            'user_two_id' => $b->id,
        ]);
        $conversation->participants()->createMany([
            ['user_id' => $a->id],
            ['user_id' => $b->id],
        ]);
        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $a->id,
        ]);

        $this->assertCount(2, $conversation->participants);
        $this->assertCount(1, $conversation->messages);
        $this->assertSame($b->id, $conversation->otherUserId($a->id));
        $this->assertSame($a->id, $conversation->otherUserId($b->id));
    }
}
```

- [ ] **Step 8: 跑測試**

```bash
docker exec wsl-backend php artisan test --compact --filter=ChatRepositoryTest
```
Expected: PASS。

- [ ] **Step 9: pint + commit**

```bash
docker exec wsl-backend vendor/bin/pint --dirty --format agent
git add app/Models database/factories tests/Feature/Chat/ChatRepositoryTest.php
git commit -m "feat(chat): add chat models, factories and relations"
```

---

## Task 3: Repositories

**Files:**
- Create: `app/Repositories/ChatConversationRepository.php`, `ChatMessageRepository.php`, `ChatConversationParticipantRepository.php`
- Test: `tests/Feature/Chat/ChatRepositoryTest.php`(擴充)

- [ ] **Step 1: 寫 repository 測試(canonical pair + 未讀)**

在 `tests/Feature/Chat/ChatRepositoryTest.php` 加入這些方法(頂部補 `use App\Repositories\ChatConversationRepository;`、`use App\Repositories\ChatMessageRepository;`):

```php
    public function test_create_with_participants_orders_pair_canonically(): void
    {
        $repo = app(ChatConversationRepository::class);
        $a = User::factory()->create();
        $b = User::factory()->create();

        $low = min($a->id, $b->id);
        $high = max($a->id, $b->id);

        $conversation = $repo->createWithParticipants($b->id, $a->id); // 故意反序

        $this->assertSame($low, $conversation->user_one_id);
        $this->assertSame($high, $conversation->user_two_id);
        $this->assertCount(2, $conversation->participants);
    }

    public function test_find_pair_is_order_independent(): void
    {
        $repo = app(ChatConversationRepository::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $created = $repo->createWithParticipants($a->id, $b->id);

        $this->assertSame($created->id, $repo->findPair($a->id, $b->id)?->id);
        $this->assertSame($created->id, $repo->findPair($b->id, $a->id)?->id);
    }

    public function test_unread_count_excludes_own_messages_and_respects_last_read(): void
    {
        $msgRepo = app(ChatMessageRepository::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = ChatConversation::factory()->create([
            'user_one_id' => min($a->id, $b->id),
            'user_two_id' => max($a->id, $b->id),
        ]);

        ChatMessage::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $b->id,
        ]);
        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $a->id, // 自己送的不算
        ]);

        // last_read_at = null → 對方的 2 則都算未讀
        $this->assertSame(2, $msgRepo->unreadCountFor($conversation->id, $a->id, null));

        // last_read_at = 現在 → 0 則未讀
        $this->assertSame(0, $msgRepo->unreadCountFor($conversation->id, $a->id, now()));
    }
```

- [ ] **Step 2: 跑測試確認失敗**

```bash
docker exec wsl-backend php artisan test --compact --filter=ChatRepositoryTest
```
Expected: FAIL(`Class "App\Repositories\ChatConversationRepository" not found` 等)。

- [ ] **Step 3: 寫 `app/Repositories/ChatConversationRepository.php`**

```php
<?php

namespace App\Repositories;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChatConversationRepository
{
    public function findPair(int $userOneId, int $userTwoId): ?ChatConversation
    {
        [$min, $max] = $this->orderPair($userOneId, $userTwoId);

        return ChatConversation::where('user_one_id', $min)
            ->where('user_two_id', $max)
            ->first();
    }

    public function createWithParticipants(int $userOneId, int $userTwoId): ChatConversation
    {
        [$min, $max] = $this->orderPair($userOneId, $userTwoId);

        return DB::transaction(function () use ($min, $max): ChatConversation {
            $conversation = ChatConversation::create([
                'user_one_id' => $min,
                'user_two_id' => $max,
            ]);

            $conversation->participants()->createMany([
                ['user_id' => $min],
                ['user_id' => $max],
            ]);

            return $conversation;
        });
    }

    /** @return Collection<int, ChatConversation> */
    public function forUser(int $userId): Collection
    {
        return ChatConversation::with(['userOne:id,name', 'userTwo:id,name', 'lastMessage'])
            ->where(function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function touchLastMessage(ChatConversation $conversation, ChatMessage $message): void
    {
        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
        ]);
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        return ChatConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    /** @return array{0: int, 1: int} */
    private function orderPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
```

- [ ] **Step 4: 寫 `app/Repositories/ChatMessageRepository.php`**

```php
<?php

namespace App\Repositories;

use App\Models\ChatMessage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class ChatMessageRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): ChatMessage
    {
        return ChatMessage::create($data);
    }

    /** @return Collection<int, ChatMessage> */
    public function paginateForConversation(int $conversationId, ?int $beforeId, int $perPage = 30): Collection
    {
        return ChatMessage::with('sender:id,name')
            ->where('conversation_id', $conversationId)
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();
    }

    public function unreadCountFor(int $conversationId, int $userId, ?CarbonInterface $lastReadAt): int
    {
        return ChatMessage::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }

    public function totalUnreadFor(int $userId): int
    {
        return ChatMessage::query()
            ->join('chat_conversation_participants as p', function ($join) use ($userId) {
                $join->on('p.conversation_id', '=', 'chat_messages.conversation_id')
                    ->where('p.user_id', '=', $userId);
            })
            ->where('chat_messages.sender_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('p.last_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'p.last_read_at');
            })
            ->count();
    }
}
```

- [ ] **Step 5: 寫 `app/Repositories/ChatConversationParticipantRepository.php`**

```php
<?php

namespace App\Repositories;

use App\Models\ChatConversationParticipant;
use Carbon\CarbonInterface;

class ChatConversationParticipantRepository
{
    public function markRead(int $conversationId, int $userId): void
    {
        ChatConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    public function lastReadAt(int $conversationId, int $userId): ?CarbonInterface
    {
        return ChatConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->value('last_read_at');
    }
}
```

- [ ] **Step 6: 跑測試確認通過**

```bash
docker exec wsl-backend php artisan test --compact --filter=ChatRepositoryTest
```
Expected: PASS。

- [ ] **Step 7: pint + commit**

```bash
docker exec wsl-backend vendor/bin/pint --dirty --format agent
git add app/Repositories tests/Feature/Chat/ChatRepositoryTest.php
git commit -m "feat(chat): add chat repositories"
```

---

## Task 4: Exception + MessageSent Event + Service

**Files:**
- Create: `app/Exceptions/ChatOperationException.php`
- Create: `app/Events/MessageSent.php`
- Create: `app/Services/ChatService.php`
- Test: `tests/Feature/Chat/ChatServiceTest.php`

- [ ] **Step 1: 產生骨架**

```bash
docker exec wsl-backend php artisan make:exception ChatOperationException --no-interaction
docker exec wsl-backend php artisan make:event MessageSent --no-interaction
docker exec wsl-backend php artisan make:class Services/ChatService --no-interaction
```

- [ ] **Step 2: 寫 service 測試**(`tests/Feature/Chat/ChatServiceTest.php`)

```php
<?php

namespace Tests\Feature\Chat;

use App\Events\MessageSent;
use App\Exceptions\ChatOperationException;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): ChatService
    {
        return app(ChatService::class);
    }

    public function test_get_or_create_is_idempotent(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $first = $this->service()->getOrCreateConversation($a->id, $b->id);
        $second = $this->service()->getOrCreateConversation($b->id, $a->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ChatConversation::count());
    }

    public function test_get_or_create_rejects_self(): void
    {
        $a = User::factory()->create();

        $this->expectException(ChatOperationException::class);
        $this->service()->getOrCreateConversation($a->id, $a->id);
    }

    public function test_assert_participant_throws_for_outsider(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->service()->getOrCreateConversation($a->id, $b->id);

        $this->expectException(ChatOperationException::class);
        $this->service()->assertParticipant($conversation->id, $outsider->id);
    }

    public function test_send_message_persists_updates_last_message_and_dispatches_event(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = $this->service()->getOrCreateConversation($a->id, $b->id);

        Event::fake([MessageSent::class]);

        $message = $this->service()->sendMessage($conversation->id, $a->id, 'hello');

        $this->assertSame('hello', $message->body);
        $this->assertSame($message->id, $conversation->fresh()->last_message_id);
        $this->assertNotNull($conversation->fresh()->last_message_at);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($message, $b) {
            return $event->message->id === $message->id && $event->recipientId === $b->id;
        });
    }

    public function test_total_unread_counts_across_conversations(): void
    {
        $me = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $conv1 = $this->service()->getOrCreateConversation($me->id, $b->id);
        $conv2 = $this->service()->getOrCreateConversation($me->id, $c->id);

        ChatMessage::factory()->count(2)->create(['conversation_id' => $conv1->id, 'sender_id' => $b->id]);
        ChatMessage::factory()->create(['conversation_id' => $conv2->id, 'sender_id' => $c->id]);
        ChatMessage::factory()->create(['conversation_id' => $conv1->id, 'sender_id' => $me->id]); // 自己的不算

        $this->assertSame(3, $this->service()->totalUnread($me->id));

        $this->service()->markAsRead($conv1->id, $me->id);
        $this->assertSame(1, $this->service()->totalUnread($me->id));
    }
}
```

- [ ] **Step 3: 跑測試確認失敗**

```bash
docker exec wsl-backend php artisan test --compact --filter=ChatServiceTest
```
Expected: FAIL(class/方法不存在)。

- [ ] **Step 4: 寫 `app/Exceptions/ChatOperationException.php`**

```php
<?php

namespace App\Exceptions;

use Exception;

/**
 * 聊天操作違反業務規則（如存取非自己參與的對話、與自己建立對話）時拋出。
 */
class ChatOperationException extends Exception
{
    //
}
```

- [ ] **Step 5: 寫 `app/Events/MessageSent.php`**

```php
<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public int $recipientId,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.user.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender?->name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: 寫 `app/Services/ChatService.php`**

```php
<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Exceptions\ChatOperationException;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Repositories\ChatConversationParticipantRepository;
use App\Repositories\ChatConversationRepository;
use App\Repositories\ChatMessageRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(
        private ChatConversationRepository $conversationRepository,
        private ChatMessageRepository $messageRepository,
        private ChatConversationParticipantRepository $participantRepository,
        private UserRepository $userRepository,
    ) {}

    /** @return array{conversations: array<int, array<string, mixed>>, users: \Illuminate\Support\Collection<int, \App\Models\User>} */
    public function getIndexData(int $userId): array
    {
        return [
            'conversations' => $this->listConversations($userId),
            'users' => $this->userRepository->getOrderedByName()
                ->reject(fn ($user) => $user->id === $userId)
                ->values(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listConversations(int $userId): array
    {
        return $this->conversationRepository->forUser($userId)
            ->map(function (ChatConversation $conversation) use ($userId): array {
                $otherId = $conversation->otherUserId($userId);
                $other = $conversation->user_one_id === $otherId
                    ? $conversation->userOne
                    : $conversation->userTwo;
                $lastReadAt = $this->participantRepository->lastReadAt($conversation->id, $userId);

                return [
                    'id' => $conversation->id,
                    'other_user' => ['id' => $other->id, 'name' => $other->name],
                    'last_message' => $conversation->lastMessage?->body,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'unread_count' => $this->messageRepository->unreadCountFor($conversation->id, $userId, $lastReadAt),
                ];
            })
            ->all();
    }

    /** @throws ChatOperationException */
    public function getOrCreateConversation(int $userId, int $targetUserId): ChatConversation
    {
        if ($userId === $targetUserId) {
            throw new ChatOperationException('無法與自己建立對話');
        }

        return $this->conversationRepository->findPair($userId, $targetUserId)
            ?? $this->conversationRepository->createWithParticipants($userId, $targetUserId);
    }

    /** @throws ChatOperationException */
    public function assertParticipant(int $conversationId, int $userId): void
    {
        if (! $this->conversationRepository->isParticipant($conversationId, $userId)) {
            throw new ChatOperationException('無權存取此對話');
        }
    }

    /**
     * @return Collection<int, ChatMessage>
     *
     * @throws ChatOperationException
     */
    public function getMessages(int $conversationId, int $userId, ?int $beforeId): Collection
    {
        $this->assertParticipant($conversationId, $userId);

        return $this->messageRepository->paginateForConversation($conversationId, $beforeId);
    }

    /** @throws ChatOperationException */
    public function sendMessage(int $conversationId, int $senderId, string $body): ChatMessage
    {
        $this->assertParticipant($conversationId, $senderId);

        $conversation = ChatConversation::findOrFail($conversationId);

        $message = DB::transaction(function () use ($conversation, $senderId, $body): ChatMessage {
            $message = $this->messageRepository->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $senderId,
                'body' => $body,
            ]);
            $this->conversationRepository->touchLastMessage($conversation, $message);

            return $message;
        });

        event(new MessageSent($message->load('sender:id,name'), $conversation->otherUserId($senderId)));

        return $message;
    }

    /** @throws ChatOperationException */
    public function markAsRead(int $conversationId, int $userId): int
    {
        $this->assertParticipant($conversationId, $userId);
        $this->participantRepository->markRead($conversationId, $userId);

        return $this->totalUnread($userId);
    }

    public function totalUnread(int $userId): int
    {
        return $this->messageRepository->totalUnreadFor($userId);
    }
}
```

- [ ] **Step 7: 跑測試確認通過**

```bash
docker exec wsl-backend php artisan test --compact --filter=ChatServiceTest
```
Expected: PASS。

- [ ] **Step 8: pint + commit**

```bash
docker exec wsl-backend vendor/bin/pint --dirty --format agent
git add app/Exceptions/ChatOperationException.php app/Events/MessageSent.php app/Services/ChatService.php tests/Feature/Chat/ChatServiceTest.php
git commit -m "feat(chat): add chat service, message event and exception"
```

---

## Task 5: Form Requests + Controller + Routes + Permission

**Files:**
- Create: `app/Http/Requests/StartConversationRequest.php`, `SendMessageRequest.php`
- Create: `app/Http/Controllers/ChatController.php`
- Modify: `routes/web.php`, `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/Chat/ChatConversationTest.php`, `ChatMessageTest.php`, `ChatUnreadTest.php`

- [ ] **Step 1: 產生骨架**

```bash
docker exec wsl-backend php artisan make:request StartConversationRequest --no-interaction
docker exec wsl-backend php artisan make:request SendMessageRequest --no-interaction
docker exec wsl-backend php artisan make:controller ChatController --no-interaction
```

- [ ] **Step 2: 寫 conversation 端點測試**(`tests/Feature/Chat/ChatConversationTest.php`)

```php
<?php

namespace Tests\Feature\Chat;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatConversationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_open_chat_index(): void
    {
        $this->actingAsAdmin();

        $this->get(route('chats.index'))->assertOk();
    }

    public function test_viewer_can_open_chat_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $this->get(route('chats.index'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('chats.index'))->assertRedirect(route('login'));
    }

    public function test_start_creates_conversation_with_two_participants(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();

        $response = $this->postJson(route('chats.start'), ['target_user_id' => $target->id]);

        $response->assertOk()->assertJsonStructure(['conversation_id']);
        $this->assertSame(1, ChatConversation::count());
        $this->assertSame(2, ChatConversation::first()->participants()->count());
    }

    public function test_start_is_idempotent_regardless_of_order(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();

        $first = $this->postJson(route('chats.start'), ['target_user_id' => $target->id])->json('conversation_id');
        $second = $this->postJson(route('chats.start'), ['target_user_id' => $target->id])->json('conversation_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ChatConversation::count());
    }

    public function test_start_rejects_self(): void
    {
        $me = $this->actingAsAdmin();

        $this->postJson(route('chats.start'), ['target_user_id' => $me->id])
            ->assertStatus(422);
    }

    public function test_start_rejects_nonexistent_user(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('chats.start'), ['target_user_id' => 999999])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 3: 寫 message 端點測試**(`tests/Feature/Chat/ChatMessageTest.php`)

```php
<?php

namespace Tests\Feature\Chat;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_participant_can_send_message_and_event_is_broadcast(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($me->id, $target->id);

        Event::fake([MessageSent::class]);

        $response = $this->postJson(route('chats.store', $conversation->id), ['body' => 'hi there']);

        $response->assertStatus(201)->assertJsonPath('message.body', 'hi there');
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $me->id,
            'body' => 'hi there',
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($target, $conversation) {
            $channels = $event->broadcastOn();

            return $event->recipientId === $target->id
                && $channels[0]->name === 'private-chat.user.'.$target->id
                && $event->broadcastWith()['conversation_id'] === $conversation->id;
        });
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $owner = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($owner->id, $target->id);

        $outsider = $this->createUserWithRole('Admin'); // 重新登入為外人

        $this->postJson(route('chats.store', $conversation->id), ['body' => 'x'])
            ->assertStatus(403);
    }

    public function test_non_participant_cannot_read_messages(): void
    {
        $owner = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($owner->id, $target->id);

        $this->createUserWithRole('Admin'); // 外人

        $this->getJson(route('chats.messages', $conversation->id))->assertStatus(403);
    }

    public function test_body_is_required(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($me->id, $target->id);

        $this->postJson(route('chats.store', $conversation->id), ['body' => ''])
            ->assertStatus(422);
    }

    public function test_messages_are_paginated_with_before_id(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($me->id, $target->id);
        $messages = ChatMessage::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $target->id,
        ]);

        $thirdId = $messages[2]->id;
        $response = $this->getJson(route('chats.messages', $conversation->id).'?before_id='.$thirdId);

        $ids = collect($response->json('messages'))->pluck('id');
        $this->assertTrue($ids->every(fn ($id) => $id < $thirdId));
    }
}
```

- [ ] **Step 4: 寫 unread 端點測試**(`tests/Feature/Chat/ChatUnreadTest.php`)

```php
<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatUnreadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unread_count_endpoint_returns_total(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($me->id, $target->id);
        ChatMessage::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $target->id,
        ]);

        $this->getJson(route('chats.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2);
    }

    public function test_mark_read_resets_unread(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($me->id, $target->id);
        ChatMessage::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $target->id,
        ]);

        $this->patchJson(route('chats.read', $conversation->id))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_non_participant_cannot_mark_read(): void
    {
        $owner = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($owner->id, $target->id);

        $this->createUserWithRole('Admin'); // 外人

        $this->patchJson(route('chats.read', $conversation->id))->assertStatus(403);
    }
}
```

- [ ] **Step 5: 跑測試確認失敗**

```bash
docker exec wsl-backend php artisan test --compact --filter="ChatConversationTest|ChatMessageTest|ChatUnreadTest"
```
Expected: FAIL(路由不存在 / 權限不存在)。

- [ ] **Step 6: 寫 `app/Http/Requests/StartConversationRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_user_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$this->user()->id])],
        ];
    }
}
```

- [ ] **Step 7: 寫 `app/Http/Requests/SendMessageRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
```

- [ ] **Step 8: 寫 `app/Http/Controllers/ChatController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Exceptions\ChatOperationException;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\StartConversationRequest;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    #[RequiresPermission('Chat.index')]
    public function index()
    {
        return view('admin.chats.index', $this->chatService->getIndexData(auth()->id()));
    }

    #[RequiresPermission('Chat.index')]
    public function conversations(): JsonResponse
    {
        return response()->json([
            'conversations' => $this->chatService->listConversations(auth()->id()),
        ]);
    }

    #[RequiresPermission('Chat.index')]
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->chatService->totalUnread(auth()->id()),
        ]);
    }

    #[RequiresPermission('Chat.index')]
    public function start(StartConversationRequest $request): JsonResponse
    {
        try {
            $conversation = $this->chatService->getOrCreateConversation(
                auth()->id(),
                (int) $request->validated('target_user_id'),
            );
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['conversation_id' => $conversation->id]);
    }

    #[RequiresPermission('Chat.index')]
    public function messages(int $conversation): JsonResponse
    {
        try {
            $messages = $this->chatService->getMessages(
                $conversation,
                auth()->id(),
                request()->integer('before_id') ?: null,
            );
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['messages' => $messages]);
    }

    #[RequiresPermission('Chat.index')]
    public function store(SendMessageRequest $request, int $conversation): JsonResponse
    {
        try {
            $message = $this->chatService->sendMessage(
                $conversation,
                auth()->id(),
                $request->validated('body'),
            );
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => $message], 201);
    }

    #[RequiresPermission('Chat.index')]
    public function markRead(int $conversation): JsonResponse
    {
        try {
            $unread = $this->chatService->markAsRead($conversation, auth()->id());
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['unread_count' => $unread]);
    }
}
```

- [ ] **Step 9: 在 `routes/web.php` 加路由**

頂部 `use` 區加 `use App\Http\Controllers\ChatController;`(依字母序)。在 `Route::middleware('permission')->group(...)` 內(其他資源路由旁)加入。**靜態路徑要放在帶參數路徑之前**:

```php
        // 即時聊天
        Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
        Route::get('/chats/conversations', [ChatController::class, 'conversations'])->name('chats.conversations');
        Route::get('/chats/unread-count', [ChatController::class, 'unreadCount'])->name('chats.unread-count');
        Route::post('/chats/start', [ChatController::class, 'start'])->name('chats.start');
        Route::get('/chats/{conversation}/messages', [ChatController::class, 'messages'])->name('chats.messages');
        Route::post('/chats/{conversation}/messages', [ChatController::class, 'store'])->name('chats.store');
        Route::patch('/chats/{conversation}/read', [ChatController::class, 'markRead'])->name('chats.read');
```

- [ ] **Step 10: 在 `database/seeders/PermissionSeeder.php` 加 Chat 模組**

在 `$modules` 陣列尾端(`Conference` 之後)加入:

```php
        'Chat' => [
            'label' => '聊天',
            'actions' => [
                'index' => '聊天',
            ],
        ],
```

> `syncRoles` 會自動讓 Admin(全部權限)與 Viewer(所有 `action='index'` 權限)取得 `Chat.index`,因此兩個內建角色開箱即可聊天。

- [ ] **Step 11: 跑測試確認通過**

```bash
docker exec wsl-backend php artisan test --compact --filter="ChatConversationTest|ChatMessageTest|ChatUnreadTest"
```
Expected: PASS(全部)。

- [ ] **Step 12: pint + commit**

```bash
docker exec wsl-backend vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php database/seeders/PermissionSeeder.php tests/Feature/Chat
git commit -m "feat(chat): add chat controller, requests, routes and permission"
```

---

## Task 6: Broadcasting 安裝 + Channels + bootstrap 註冊

**Files:**
- Create/Modify(由套件指令產生): `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`
- Modify: `bootstrap/app.php`(確認 `withBroadcasting`)
- Modify: `phpunit.xml`(若 channel 授權測試需要簽章)
- Test: `tests/Feature/Chat/ChatChannelAuthTest.php`

- [ ] **Step 1: 安裝 Reverb 與 broadcasting**

```bash
docker exec wsl-backend composer require laravel/reverb
docker exec wsl-backend php artisan install:broadcasting --no-interaction
```
Expected: 產生 `config/broadcasting.php`、`config/reverb.php`、`routes/channels.php`,並安裝 npm `laravel-echo`、`pusher-js`。

- [ ] **Step 2: 確認 `bootstrap/app.php` 已註冊 channels 路由**

```bash
docker exec wsl-backend grep -n "withBroadcasting\|channels" bootstrap/app.php
```
若**沒有**看到 `withBroadcasting`,手動在 `->withRouting(...)` 之後加入(已知 install 在 channels.php 已存在時不會自動加):

```php
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web']],
    )
```

- [ ] **Step 3: 覆寫 `routes/channels.php` 為聊天頻道**

```php
<?php

use App\Models\ChatConversation;
use Illuminate\Support\Facades\Broadcast;

// 私有頻道：使用者個人頻道，承載 message.sent（驅動未讀 badge 與開啟中的對話）
Broadcast::channel('chat.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

// 私有頻道：對話頻道，僅承載「輸入中」whisper
Broadcast::channel('chat.conversation.{conversationId}', function ($user, int $conversationId) {
    return ChatConversation::where('id', $conversationId)
        ->where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id);
        })
        ->exists();
});

// Presence 頻道：全後台線上狀態
Broadcast::channel('chat.online', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});
```

- [ ] **Step 4: 寫 channel 授權測試**(`tests/Feature/Chat/ChatChannelAuthTest.php`)

> 透過 `/broadcasting/auth` 端點驗證授權結果(授權通過 200、拒絕 403、未登入 403/redirect)。

```php
<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatChannelAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function authChannel(string $channel): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/broadcasting/auth', [
            'channel_name' => $channel,
            'socket_id' => '123.456',
        ]);
    }

    public function test_user_can_authorize_own_user_channel(): void
    {
        $me = $this->actingAsAdmin();

        $this->authChannel('private-chat.user.'.$me->id)->assertOk();
    }

    public function test_user_cannot_authorize_another_user_channel(): void
    {
        $me = $this->actingAsAdmin();
        $other = User::factory()->create();

        $this->authChannel('private-chat.user.'.$other->id)->assertForbidden();
    }

    public function test_participant_can_authorize_conversation_channel(): void
    {
        $me = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($me->id, $target->id);

        $this->authChannel('private-chat.conversation.'.$conversation->id)->assertOk();
    }

    public function test_outsider_cannot_authorize_conversation_channel(): void
    {
        $owner = $this->actingAsAdmin();
        $target = User::factory()->create();
        $conversation = app(ChatService::class)->getOrCreateConversation($owner->id, $target->id);

        $this->createUserWithRole('Admin'); // 外人

        $this->authChannel('private-chat.conversation.'.$conversation->id)->assertForbidden();
    }

    public function test_authenticated_user_can_join_presence_channel(): void
    {
        $this->actingAsAdmin();

        $this->authChannel('presence-chat.online')->assertOk();
    }

    public function test_guest_cannot_authorize_channel(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-chat.online',
            'socket_id' => '123.456',
        ])->assertStatus(403);
    }
}
```

- [ ] **Step 5: 跑 channel 授權測試**

```bash
docker exec wsl-backend php artisan test --compact --filter=ChatChannelAuthTest
```
Expected: PASS。
> 若「授權通過」案例因 `BROADCAST_CONNECTION=null` 無法產生簽章而非 200,改在 `phpunit.xml` 的 `<php>` 區把 `BROADCAST_CONNECTION` 設為 `reverb` 並補 dummy env:`REVERB_APP_ID=test`、`REVERB_APP_KEY=test`、`REVERB_APP_SECRET=test`,再重跑。

- [ ] **Step 6: 回歸全部後端測試**

```bash
docker exec wsl-backend php artisan test --compact --filter=Chat
```
Expected: 所有 Chat 測試 PASS。

- [ ] **Step 7: pint + commit**

```bash
docker exec wsl-backend vendor/bin/pint --dirty --format agent
git add bootstrap/app.php routes/channels.php config composer.json composer.lock package.json package-lock.json phpunit.xml tests/Feature/Chat/ChatChannelAuthTest.php
git commit -m "feat(chat): install reverb broadcasting and channel authorization"
```

---

## Task 7: Docker Reverb Service + .env

**Files:**
- Modify: `/home/michaelhao/workspace/docker-compose.yml`
- Modify: `backend/.env`, `backend/.env.example`

- [ ] **Step 1: 在 `.env` 與 `.env.example` 設定 Reverb**

設定/調整下列鍵(install:broadcasting 已寫入大部分,需確認 docker 專屬的 host 值):

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
# app（backend-api 容器）推送目標 → 用 reverb 容器名
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
# reverb server 綁定位址
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# 瀏覽器（Echo client）連線位址
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```
> `.env.example` 用相同 key、值留空或範例值(不含真實密鑰)。

- [ ] **Step 2: 在 `/home/michaelhao/workspace/docker-compose.yml` 新增 reverb service**

於 `services:` 下加入(與 `backend-api` 同層):

```yaml
  # 即時聊天 WebSocket 伺服器
  reverb:
    image: backend
    container_name: wsl-reverb
    working_dir: /var/www/html
    volumes:
      - ./backend:/var/www/html
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    ports:
      - "8080:8080"
    networks:
      - app-network
    depends_on:
      - db
      - backend-api
```

- [ ] **Step 3: 啟動 reverb service 並驗證**

```bash
docker compose -f /home/michaelhao/workspace/docker-compose.yml up -d reverb
docker logs wsl-reverb --tail 20
```
Expected: 日誌顯示 Reverb server starting / listening on 0.0.0.0:8080,無 fatal error。

- [ ] **Step 4: 驗證 broadcasting auth 端點(已登入情境用測試已涵蓋;此處確認服務啟動)**

```bash
docker exec wsl-backend php artisan config:show broadcasting.default
```
Expected: `reverb`。

- [ ] **Step 5: commit**

```bash
git add backend/.env.example
git commit -m "feat(chat): add reverb env config and docker service"
```
> 注意:`.env`(實際密鑰)與 `docker-compose.yml`(在 repo 外的 workspace 根目錄)依專案 git 策略決定是否納管;`docker-compose.yml` 不在 backend repo 內,通常另行處理。

---

## Task 8: 前端 — Echo、聊天頁、未讀 badge

**Files:**
- Modify: `resources/js/bootstrap.js`, `resources/views/layouts/admin.blade.php`, `vite.config.js`
- Create: `resources/views/admin/chats/index.blade.php`, `resources/js/chats/index.js`

- [ ] **Step 1: 安裝前端套件(若 Task 6 未裝齊)**

```bash
docker exec wsl-backend npm install --save-dev laravel-echo pusher-js
```

- [ ] **Step 2: 改 `resources/js/bootstrap.js` 初始化 Echo + 全站未讀 badge**

```js
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const userIdMeta = document.querySelector('meta[name="user-id"]');

if (reverbKey && userIdMeta) {
    window.currentUserId = Number(userIdMeta.content);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    initChatBadge();
}

function initChatBadge() {
    const badge = document.getElementById('chat-unread-badge');
    if (!badge) {
        return;
    }

    const setBadge = (count) => {
        badge.textContent = count;
        badge.classList.toggle('hidden', count <= 0);
    };

    window.axios.get('/chats/unread-count')
        .then(({ data }) => setBadge(data.unread_count))
        .catch(() => {});

    window.Echo.private(`chat.user.${window.currentUserId}`)
        .listen('.message.sent', (event) => {
            // 廣播給聊天頁處理（若該頁開著）
            window.dispatchEvent(new CustomEvent('chat:message', { detail: event }));

            // 若使用者沒在看這個對話，未讀 +1
            if (window.chatActiveConversationId !== event.conversation_id) {
                const current = parseInt(badge.textContent || '0', 10) || 0;
                setBadge(current + 1);
            }
        });
}
```

- [ ] **Step 3: 在 `vite.config.js` 的 `input` 陣列加入聊天頁 entry**

於 `'resources/js/conferences/index.js',` 後加一行:
```js
                'resources/js/chats/index.js',
```

- [ ] **Step 4: 改 `resources/views/layouts/admin.blade.php`**

(a) 在 `<head>` 既有 meta 區(`login-url` meta 之後)加入(只給已登入者用,Auth::id() 必有值):
```blade
    <meta name="user-id" content="{{ Auth::id() }}">
```

(b) 在側邊欄導覽,於 `Conference.index` 區塊之後加入聊天入口(含未讀 badge,`hidden` 為 JS 契約 class 保留字面):
```blade
                <x-permission name="Chat.index">
                    <a href="{{ route('chats.index') }}"
                       class="flex items-center justify-between px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('chats.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        <span>聊天</span>
                        <span id="chat-unread-badge"
                              class="hidden inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-xs"></span>
                    </a>
                </x-permission>
```

- [ ] **Step 5: 寫聊天頁 `resources/views/admin/chats/index.blade.php`**

```blade
@extends('layouts.admin')

@section('page-title', '聊天')

@section('content')
    <div id="chat-app"
         data-user-id="{{ Auth::id() }}"
         class="card flex h-[calc(100vh-8rem)] overflow-hidden p-0">

        {{-- 左欄：對話列表 --}}
        <div class="w-72 border-r border-slate-200 flex flex-col flex-shrink-0">
            <div class="p-3 border-b border-slate-200">
                <select id="start-user-select" class="form-control">
                    <option value="">＋ 開新對話…</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <ul id="conversation-list" class="flex-1 overflow-y-auto"></ul>
        </div>

        {{-- 右欄：訊息串 --}}
        <div class="flex-1 flex flex-col">
            <div class="h-14 border-b border-slate-200 flex items-center px-4 gap-2">
                <span id="chat-online-dot" class="hidden w-2.5 h-2.5 rounded-full bg-green-500"></span>
                <span id="chat-title" class="font-medium text-slate-700">選擇一個對話開始聊天</span>
            </div>
            <div id="message-thread" class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50"></div>
            <div id="typing-indicator" class="hidden px-4 py-1 text-xs text-slate-400">對方正在輸入…</div>
            <form id="message-form" class="hidden border-t border-slate-200 p-3 flex gap-2">
                <input id="message-input" type="text" autocomplete="off"
                       class="form-control flex-1" placeholder="輸入訊息…">
                <button type="submit" class="btn-primary">送出</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/chats/index.js')
@endpush
```

- [ ] **Step 6: 寫聊天頁 JS `resources/js/chats/index.js`**

```js
const root = document.getElementById('chat-app');

if (root) {
    const meId = Number(root.dataset.userId);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const els = {
        list: document.getElementById('conversation-list'),
        thread: document.getElementById('message-thread'),
        form: document.getElementById('message-form'),
        input: document.getElementById('message-input'),
        typing: document.getElementById('typing-indicator'),
        title: document.getElementById('chat-title'),
        onlineDot: document.getElementById('chat-online-dot'),
        userSelect: document.getElementById('start-user-select'),
    };

    let activeId = null;
    let activeOtherId = null;
    let conversationChannel = null;
    let typingTimer = null;
    let hideTypingTimer = null;
    const onlineUsers = new Set();

    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    };

    const renderConversations = (conversations) => {
        els.list.innerHTML = '';
        conversations.forEach((c) => {
            const li = document.createElement('li');
            li.dataset.id = c.id;
            li.dataset.otherId = c.other_user.id;
            li.dataset.otherName = c.other_user.name;
            li.className = 'px-3 py-2.5 border-b border-slate-100 cursor-pointer hover:bg-slate-50 flex items-center justify-between';
            li.innerHTML = `
                <div class="min-w-0">
                    <div class="text-sm font-medium text-slate-700 truncate">${escapeHtml(c.other_user.name)}</div>
                    <div class="text-xs text-slate-400 truncate">${escapeHtml(c.last_message || '')}</div>
                </div>
                <span class="conv-unread ${c.unread_count > 0 ? '' : 'hidden'} inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-xs">${c.unread_count}</span>
            `;
            li.addEventListener('click', () => openConversation(c.id, c.other_user.id, c.other_user.name));
            els.list.appendChild(li);
        });
    };

    const loadConversations = () =>
        window.axios.get('/chats/conversations').then(({ data }) => renderConversations(data.conversations));

    const appendMessage = (msg) => {
        const mine = Number(msg.sender_id) === meId;
        const wrap = document.createElement('div');
        wrap.className = `flex ${mine ? 'justify-end' : 'justify-start'}`;
        wrap.innerHTML = `<div class="max-w-[70%] px-3 py-2 rounded-lg text-sm ${mine ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700'}">${escapeHtml(msg.body)}</div>`;
        els.thread.appendChild(wrap);
        els.thread.scrollTop = els.thread.scrollHeight;
    };

    const subscribeConversation = (id) => {
        if (conversationChannel) {
            window.Echo.leave(`chat.conversation.${conversationChannel}`);
        }
        conversationChannel = id;
        window.Echo.private(`chat.conversation.${id}`)
            .listenForWhisper('typing', () => {
                els.typing.classList.remove('hidden');
                clearTimeout(hideTypingTimer);
                hideTypingTimer = setTimeout(() => els.typing.classList.add('hidden'), 2000);
            });
    };

    const openConversation = async (id, otherId, otherName) => {
        activeId = id;
        activeOtherId = otherId;
        window.chatActiveConversationId = id;
        els.title.textContent = otherName;
        els.form.classList.remove('hidden');
        els.typing.classList.add('hidden');
        els.thread.innerHTML = '';

        const { data } = await window.axios.get(`/chats/${id}/messages`);
        // 後端回傳為 id desc，前端反轉成時間正序
        data.messages.slice().reverse().forEach(appendMessage);

        await window.axios.patch(`/chats/${id}/read`);
        updateOnlineDot();
        loadConversations();
        refreshGlobalBadge();
    };

    const refreshGlobalBadge = () => {
        const badge = document.getElementById('chat-unread-badge');
        if (!badge) return;
        window.axios.get('/chats/unread-count').then(({ data }) => {
            badge.textContent = data.unread_count;
            badge.classList.toggle('hidden', data.unread_count <= 0);
        });
    };

    const updateOnlineDot = () => {
        els.onlineDot.classList.toggle('hidden', !(activeOtherId && onlineUsers.has(activeOtherId)));
    };

    // 送訊息
    els.form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = els.input.value.trim();
        if (!body || !activeId) return;
        els.input.value = '';
        const { data } = await window.axios.post(`/chats/${activeId}/messages`, { body });
        appendMessage(data.message);
        loadConversations();
    });

    // 輸入中 whisper
    els.input.addEventListener('input', () => {
        if (!activeId) return;
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {}, 0);
        window.Echo.private(`chat.conversation.${activeId}`).whisper('typing', { from: meId });
    });

    // 開新對話
    els.userSelect.addEventListener('change', async () => {
        const targetId = els.userSelect.value;
        if (!targetId) return;
        const { data } = await window.axios.post('/chats/start', { target_user_id: Number(targetId) });
        els.userSelect.value = '';
        await loadConversations();
        const opt = [...els.userSelect.options].find((o) => o.value === targetId);
        openConversation(data.conversation_id, Number(targetId), opt ? opt.textContent : '');
    });

    // 收到他人訊息（來自 bootstrap.js 的全站 chat.user 訂閱）
    window.addEventListener('chat:message', (e) => {
        const msg = e.detail;
        if (msg.conversation_id === activeId) {
            appendMessage(msg);
            window.axios.patch(`/chats/${activeId}/read`).then(refreshGlobalBadge);
        }
        loadConversations();
    });

    // Presence：線上狀態
    window.Echo.join('chat.online')
        .here((users) => {
            users.forEach((u) => onlineUsers.add(Number(u.id)));
            updateOnlineDot();
        })
        .joining((u) => { onlineUsers.add(Number(u.id)); updateOnlineDot(); })
        .leaving((u) => { onlineUsers.delete(Number(u.id)); updateOnlineDot(); });

    // 開新對話下拉沿用 form-control 樣式即可；初次載入對話列表
    loadConversations();

    // openConversation 內已 subscribeConversation
    const originalOpen = openConversation;
    // 確保切換對話時訂閱對話頻道（whisper）
    els.list.addEventListener('click', () => {
        if (activeId) subscribeConversation(activeId);
    });
}
```

> 註:`subscribeConversation` 在點擊對話後綁定 whisper 頻道。若想更精簡,可把 `subscribeConversation(id)` 直接放進 `openConversation` 內(在取得訊息後呼叫)。執行時以實際行為驗證為準。

- [ ] **Step 7: 建置前端**

```bash
docker exec wsl-backend npm run build
```
Expected: build 成功,無錯誤,manifest 含 `resources/js/chats/index.js`。

- [ ] **Step 8: 手動驗證(雙帳號)**

1. 確認 `wsl-reverb` 執行中(`docker logs wsl-reverb`)。
2. 用兩個瀏覽器(或一般+無痕)各登入不同後台帳號。
3. A 在聊天頁從「開新對話」選 B → 送訊息。
4. 驗證:B 在任一頁面側欄「聊天」紅點 +1;B 開啟該對話即時看到訊息、紅點歸零;A 送出後本地立即顯示。
5. 瀏覽器 console 應看到 WebSocket 連線(101 Switching Protocols)。

- [ ] **Step 9: commit**

```bash
git add resources vite.config.js
git commit -m "feat(chat): add chat UI, echo client and unread badge"
```

---

## Task 9: 線上狀態 + 輸入中(驗證與微調)

> presence(`chat.online`)與 typing whisper 已寫在 Task 8 的 JS。本任務專注端到端驗證與修正。

- [ ] **Step 1: 驗證線上狀態**

雙帳號登入聊天頁,A 開啟與 B 的對話 → B 在線時 A 的標題列綠點亮;B 關閉分頁/登出後綠點消失(`leaving`)。

- [ ] **Step 2: 驗證輸入中**

A 在輸入框打字 → B(開著同一對話)看到「對方正在輸入…」;A 停止 2 秒後提示消失。

- [ ] **Step 3: 若行為不符,修正 `resources/js/chats/index.js` 中的 presence/whisper 區塊,重新 `npm run build` 驗證。**

- [ ] **Step 4: commit(若有修正)**

```bash
git add resources/js/chats/index.js
git commit -m "fix(chat): refine presence and typing indicators"
```

---

## Task 10: 全測試回歸 + 最終整理

- [ ] **Step 1: 跑全部 Chat 測試**

```bash
docker exec wsl-backend php artisan test --compact --filter=Chat
```
Expected: 全 PASS。

- [ ] **Step 2: 跑整套測試確認無回歸**

```bash
docker exec wsl-backend php artisan test --compact
```
Expected: 全 PASS。

- [ ] **Step 3: 最終 pint**

```bash
docker exec wsl-backend vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: 確認 git 狀態乾淨、各任務已 commit**

```bash
git status
git log --oneline -10
```

---

## Self-Review

**Spec coverage(對照已核准設計):**
- 一對一私訊:Task 1–5(canonical pair、participants、訊息端點)✓
- 即時 Reverb:Task 6–8(install、channels、Echo、docker reverb)✓
- 未讀數/紅點:Task 5(unread 端點/markRead)+ Task 8(badge + 即時 +1)✓
- 線上狀態:Task 8–9(presence `chat.online` + 綠點)✓
- 輸入中:Task 8–9(`chat.conversation` whisper)✓
- 所有使用者互通:Task 5(PermissionSeeder Chat 模組,Admin/Viewer 自動取得 `Chat.index`)✓
- 不做群組/附件/編輯刪除:計畫未含 ✓

**Placeholder scan:** 無 TODO/TBD;所有 step 含實際程式碼或指令。前端 JS 的 `subscribeConversation` 綁定方式附了註記,執行時以行為驗證為準(非 placeholder,是可運作的實作 + 簡化建議)。

**Type/命名一致性:** Service 方法(`getOrCreateConversation`/`listConversations`/`getIndexData`/`getMessages`/`sendMessage`/`markAsRead`/`totalUnread`/`assertParticipant`)、Repository 方法(`findPair`/`createWithParticipants`/`forUser`/`touchLastMessage`/`isParticipant`/`paginateForConversation`/`unreadCountFor`/`totalUnreadFor`/`markRead`/`lastReadAt`)、路由名(`chats.index`/`chats.conversations`/`chats.unread-count`/`chats.start`/`chats.messages`/`chats.store`/`chats.read`)、事件(`MessageSent` + `broadcastAs('message.sent')` + 前端 `.listen('.message.sent')`)、頻道名(`chat.user.{id}`/`chat.conversation.{id}`/`chat.online`)在各任務間一致 ✓

---

## Execution Handoff

計畫完成。兩種執行方式:

1. **Subagent-Driven(建議)** — 每個 task 派一個新 subagent 執行,task 間做兩階段 review,迭代快。
2. **Inline Execution** — 在本 session 用 executing-plans 批次執行,設檢查點 review。

(注意:本專案所有指令需在 docker 容器內跑,且 commit 依使用者 git 紀律,需確認後才執行。)
