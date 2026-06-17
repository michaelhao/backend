<?php

namespace App\Repositories;

use App\Models\ChatConversationParticipant;
class ChatConversationParticipantRepository
{
    public function markRead(int $conversationId, int $userId): void
    {
        ChatConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }
}
