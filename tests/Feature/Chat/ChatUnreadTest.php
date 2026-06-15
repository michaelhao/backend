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
