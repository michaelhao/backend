<?php

namespace App\Repositories;

use App\Models\ChatMessage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class ChatMessageRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): ChatMessage
    {
        return ChatMessage::create($data);
    }

    /** @return Collection<int, ChatMessage> */
    public function paginateForConversation(int $conversationId, ?int $beforeId, int $perPage = 30): Collection
    {
        return ChatMessage::with('sender:id,name')
            ->where('conversation_id', $conversationId)
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();
    }

    public function unreadCountFor(int $conversationId, int $userId, ?CarbonInterface $lastReadAt): int
    {
        return ChatMessage::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }

    /**
     * @return array<int, int> conversationId => unread count
     */
    public function unreadCountsByConversation(int $userId): array
    {
        return ChatMessage::query()
            ->join('chat_conversation_participants as p', function ($join) use ($userId) {
                $join->on('p.conversation_id', '=', 'chat_messages.conversation_id')
                    ->where('p.user_id', '=', $userId);
            })
            ->where('chat_messages.sender_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('p.last_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'p.last_read_at');
            })
            ->groupBy('chat_messages.conversation_id')
            ->selectRaw('chat_messages.conversation_id as conversation_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'conversation_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function totalUnreadFor(int $userId): int
    {
        // 與 unreadCountsByConversation 共用同一份未讀定義，避免兩處 SQL 各寫一遍而走樣
        return array_sum($this->unreadCountsByConversation($userId));
    }
}
