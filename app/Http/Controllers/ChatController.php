<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Exceptions\ChatOperationException;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\StartConversationRequest;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    #[RequiresPermission('Chat.index')]
    public function index()
    {
        return view('admin.chats.index', $this->chatService->getIndexData(auth()->id()));
    }

    #[RequiresPermission('Chat.index')]
    public function conversations(): JsonResponse
    {
        return response()->json([
            'conversations' => $this->chatService->listConversations(auth()->id()),
        ]);
    }

    #[RequiresPermission('Chat.index')]
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->chatService->totalUnread(auth()->id()),
        ]);
    }

    #[RequiresPermission('Chat.index')]
    public function start(StartConversationRequest $request): JsonResponse
    {
        try {
            $conversation = $this->chatService->getOrCreateConversation(
                auth()->id(),
                (int) $request->validated('target_user_id'),
            );
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['conversation_id' => $conversation->id]);
    }

    #[RequiresPermission('Chat.index')]
    public function messages(int $conversation): JsonResponse
    {
        try {
            $messages = $this->chatService->getMessages(
                $conversation,
                auth()->id(),
                request()->integer('before_id') ?: null,
            );
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['messages' => $messages]);
    }

    #[RequiresPermission('Chat.index')]
    public function store(SendMessageRequest $request, int $conversation): JsonResponse
    {
        try {
            $message = $this->chatService->sendMessage(
                $conversation,
                auth()->id(),
                $request->validated('body'),
            );
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => $message], 201);
    }

    #[RequiresPermission('Chat.index')]
    public function markRead(int $conversation): JsonResponse
    {
        try {
            $unread = $this->chatService->markAsRead($conversation, auth()->id());
        } catch (ChatOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['unread_count' => $unread]);
    }
}
