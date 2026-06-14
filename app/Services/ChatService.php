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
        return $this->conversationRepository->forUser($userId)
            ->map(function (ChatConversation $conversation) use ($userId): array {
                $otherId = $conversation->otherUserId($userId);
                $other = $conversation->user_one_id === $otherId
                    ? $conversation->userOne
                    : $conversation->userTwo;
                $lastReadAt = $this->participantRepository->lastReadAt($conversation->id, $userId);

                return [
                    'id' => $conversation->id,
                    'other_user' => ['id' => $other->id, 'name' => $other->name],
                    'last_message' => $conversation->lastMessage?->body,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'unread_count' => $this->messageRepository->unreadCountFor($conversation->id, $userId, $lastReadAt),
                ];
            })
            ->all();
    }

    /** @throws ChatOperationException */
    public function getOrCreateConversation(int $userId, int $targetUserId): ChatConversation
    {
        if ($userId === $targetUserId) {
            throw new ChatOperationException('無法與自己建立對話');
        }

        return $this->conversationRepository->findPair($userId, $targetUserId)
            ?? $this->conversationRepository->createWithParticipants($userId, $targetUserId);
    }

    /** @throws ChatOperationException */
    public function assertParticipant(int $conversationId, int $userId): void
    {
        if (! $this->conversationRepository->isParticipant($conversationId, $userId)) {
            throw new ChatOperationException('無權存取此對話');
        }
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
        $this->assertParticipant($conversationId, $senderId);

        $conversation = ChatConversation::findOrFail($conversationId);

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
