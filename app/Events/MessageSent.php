<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public int $recipientId,
    ) {}

    /**
     * 廣播給接收者（驅動其未讀/即時顯示），同時也廣播給送出者自己的頻道，
     * 讓送出者的其他分頁/裝置也能即時看到剛送出的訊息（前端以訊息 id 去重避免重複顯示）。
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.user.'.$this->recipientId),
            new PrivateChannel('chat.user.'.$this->message->sender_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender?->name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
