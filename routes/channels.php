<?php

use App\Models\ChatConversation;
use Illuminate\Support\Facades\Broadcast;

// 私有頻道：使用者個人頻道，承載 message.sent（驅動未讀 badge 與開啟中的對話）
Broadcast::channel('chat.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

// 私有頻道：對話頻道，僅承載「輸入中」whisper
Broadcast::channel('chat.conversation.{conversationId}', function ($user, int $conversationId) {
    return ChatConversation::where('id', $conversationId)
        ->where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id);
        })
        ->exists();
});

// Presence 頻道：全後台線上狀態
Broadcast::channel('chat.online', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});
