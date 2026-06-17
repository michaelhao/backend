<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Exceptions\ChatOperationException;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Repositories\ChatConversationParticipantRepository;
use App\Repositories\ChatConversationRepository;
use App\Repositories\ChatMessageRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(
        private ChatConversationRepository $conversationRepository,
        private ChatMessageRepository $messageRepository,
        private ChatConversationParticipantRepository $participantRepository,
        private UserRepository $userRepository,
    ) {}

    /** @return array{conversations: array<int, array<string, mixed>>, users: \Illuminate\Support\Collection<int, \App\Models\User>} */
    public function getIndexData(int $userId): array
    {
        return [
            'conversations' => $this->listConversations($userId),
            'users' => $this->userRepository->getOrderedByName()
                ->reject(fn ($user) => (int) $user->id === $userId)
                ->values(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listConversations(int $userId): array
    {
        $unreadCounts = $this->messageRepository->unreadCountsByConversation($userId);

        return $this->conversationRepository->forUser($userId)
            ->map(function (ChatConversation $conversation) use ($userId, $unreadCounts): ?array {
                $otherId = $conversation->otherUserId($userId);
                $other = $conversation->user_one_id === $otherId
                    ? $conversation->userOne
                    : $conversation->userTwo;

                // 對方使用者已被刪除（本專案無 DB 外鍵）→ 略過此孤兒對話，避免存取 null 而 500
                if (! $other) {
                    return null;
                }

                return [
                    'id' => $conversation->id,
                    'other_user' => ['id' => $other->id, 'name' => $other->name],
                    'last_message' => $conversation->lastMessage?->body,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'unread_count' => $unreadCounts[$conversation->id] ?? 0,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @throws ChatOperationException */
    public function getOrCreateConversation(int $userId, int $targetUserId): ChatConversation
    {
        if ($userId === $targetUserId) {
            throw new ChatOperationException('無法與自己建立對話');
        }

        $existing = $this->conversationRepository->findPair($userId, $targetUserId);
        if ($existing) {
            return $existing;
        }

        try {
            return $this->conversationRepository->createWithParticipants($userId, $targetUserId);
        } catch (QueryException $e) {
            // 並發下另一請求已建立同一對話（撞 unique index）→ 取回既有的，而非回 500
            $conversation = $this->conversationRepository->findPair($userId, $targetUserId);
            if (! $conversation) {
                throw $e;
            }

            return $conversation;
        }
    }

    /**
     * 確認對話存在且 user 為其參與者，並回傳該對話。
     * 對話不存在 → ModelNotFoundException（404）；存在但非參與者 → ChatOperationException（403）。
     * 參與者即 canonical pair 的 user_one_id / user_two_id，故以對話自身欄位判斷，省一次 participants 查詢。
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws ChatOperationException
     */
    public function assertParticipant(int $conversationId, int $userId): ChatConversation
    {
        $conversation = $this->conversationRepository->findOrFail($conversationId);

        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            throw new ChatOperationException('無權存取此對話');
        }

        return $conversation;
    }

    /**
     * @return Collection<int, ChatMessage>
     *
     * @throws ChatOperationException
     */
    public function getMessages(int $conversationId, int $userId, ?int $beforeId): Collection
    {
        $this->assertParticipant($conversationId, $userId);

        return $this->messageRepository->paginateForConversation($conversationId, $beforeId);
    }

    /** @throws ChatOperationException */
    public function sendMessage(int $conversationId, int $senderId, string $body): ChatMessage
    {
        $conversation = $this->assertParticipant($conversationId, $senderId);

        $message = DB::transaction(function () use ($conversation, $senderId, $body): ChatMessage {
            $message = $this->messageRepository->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $senderId,
                'body' => $body,
            ]);
            $this->conversationRepository->touchLastMessage($conversation, $message);

            return $message;
        });

        event(new MessageSent($message->load('sender:id,name'), $conversation->otherUserId($senderId)));

        return $message;
    }

    /** @throws ChatOperationException */
    public function markAsRead(int $conversationId, int $userId): int
    {
        $this->assertParticipant($conversationId, $userId);
        $this->participantRepository->markRead($conversationId, $userId);

        return $this->totalUnread($userId);
    }

    public function totalUnread(int $userId): int
    {
        return $this->messageRepository->totalUnreadFor($userId);
    }
}
