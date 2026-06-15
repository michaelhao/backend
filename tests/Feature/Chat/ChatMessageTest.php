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

        $this->createUserWithRole('Admin'); // 重新登入為外人

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
