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
