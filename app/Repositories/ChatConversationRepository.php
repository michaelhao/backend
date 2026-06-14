<?php

namespace App\Repositories;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChatConversationRepository
{
    public function findPair(int $userOneId, int $userTwoId): ?ChatConversation
    {
        [$min, $max] = $this->orderPair($userOneId, $userTwoId);

        return ChatConversation::where('user_one_id', $min)
            ->where('user_two_id', $max)
            ->first();
    }

    public function findOrFail(int $id): ChatConversation
    {
        return ChatConversation::findOrFail($id);
    }

    public function createWithParticipants(int $userOneId, int $userTwoId): ChatConversation
    {
        [$min, $max] = $this->orderPair($userOneId, $userTwoId);

        return DB::transaction(function () use ($min, $max): ChatConversation {
            $conversation = ChatConversation::create([
                'user_one_id' => $min,
                'user_two_id' => $max,
            ]);

            $conversation->participants()->createMany([
                ['user_id' => $min],
                ['user_id' => $max],
            ]);

            return $conversation;
        });
    }

    /** @return Collection<int, ChatConversation> */
    public function forUser(int $userId): Collection
    {
        return ChatConversation::with(['userOne:id,name', 'userTwo:id,name', 'lastMessage'])
            ->where(function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function touchLastMessage(ChatConversation $conversation, ChatMessage $message): void
    {
        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
        ]);
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        return ChatConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    /** @return array{0: int, 1: int} */
    private function orderPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
