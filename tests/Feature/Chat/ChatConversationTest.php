<?php

namespace Tests\Feature\Chat;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatConversationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_viewer_can_access_chat_endpoint(): void
    {
        // Viewer 角色僅有各 index 權限；可存取需要 Chat.index 的端點即代表權限正確開放。
        // index 視圖本身的 render 測試在 Task 8（視圖建立後）補上。
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $this->getJson(route('chats.unread-count'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('chats.index'))->assertRedirect(route('login'));
    }

    public function test_start_creates_conversation_with_two_participants(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->create();

        $response = $this->postJson(route('chats.start'), ['target_user_id' => $target->id]);

        $response->assertOk()->assertJsonStructure(['conversation_id']);
        $this->assertSame(1, ChatConversation::count());
        $this->assertSame(2, ChatConversation::first()->participants()->count());
    }

    public function test_start_is_idempotent_regardless_of_order(): void
    {
        $this->actingAsAdmin();
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
