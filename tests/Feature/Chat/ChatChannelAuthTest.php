<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChatChannelAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 測試環境預設 BROADCAST_CONNECTION=null，null broadcaster 不執行頻道授權（一律放行）。
        // 改用 reverb（pusher 協定）broadcaster 才會真正跑授權 callback 並對拒絕回 403。
        // 直接注入 dummy 金鑰：授權邏輯與金鑰值無關，簽章只是本地 HMAC、不需連線。
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app-id',
        ]);

        // 頻道定義是在 boot 時註冊到「當時的預設 broadcaster」（測試環境為 null）。
        // 切換成 reverb 後需重新載入 channels.php，讓頻道授權註冊到 reverb broadcaster。
        require base_path('routes/channels.php');
    }

    private function authChannel(string $channel): TestResponse
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
        $this->actingAsAdmin();
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
