<?php

use App\Repositories\ChatConversationRepository;
use Illuminate\Support\Facades\Broadcast;

// 私有頻道：使用者個人頻道，承載 message.sent（驅動未讀 badge 與開啟中的對話）
Broadcast::channel('chat.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

// 私有頻道：對話頻道，僅承載「輸入中」whisper。參與者判斷重用 repository，與其他層一致
Broadcast::channel('chat.conversation.{conversationId}', function ($user, int $conversationId) {
    return app(ChatConversationRepository::class)->isParticipant($conversationId, (int) $user->id);
});

// Presence 頻道：全後台線上狀態
Broadcast::channel('chat.online', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});
