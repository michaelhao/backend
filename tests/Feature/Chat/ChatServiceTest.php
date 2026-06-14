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
        ChatMessage::factory()->create(['conversation_id' => $conv1->id, 'sender_id' => $me->id]);

        $this->assertSame(3, $this->service()->totalUnread($me->id));

        $this->service()->markAsRead($conv1->id, $me->id);
        $this->assertSame(1, $this->service()->totalUnread($me->id));
    }
}
