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
