<?php

namespace Tests\Feature\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Repositories\ChatConversationRepository;
use App\Repositories\ChatMessageRepository;
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

        $this->assertSame(2, $msgRepo->unreadCountFor($conversation->id, $a->id, null));
        $this->assertSame(0, $msgRepo->unreadCountFor($conversation->id, $a->id, now()));
    }
}
