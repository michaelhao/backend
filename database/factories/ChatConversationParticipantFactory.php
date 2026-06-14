<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversationParticipant>
 */
class ChatConversationParticipantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'user_id' => User::factory(),
            'last_read_at' => null,
        ];
    }
}
