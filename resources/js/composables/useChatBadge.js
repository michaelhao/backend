import http from '@/lib/http';

export function useChatBadge() {
    const refresh = async () => {
        const badge = document.getElementById('chat-unread-badge');
        if (!badge) {
            return;
        }
        try {
            const { data } = await http.get('/chats/unread-count');
            badge.textContent = data.unread_count;
            badge.classList.toggle('hidden', data.unread_count <= 0);
        } catch {
            // 靜默吞錯，避免非關鍵 badge 影響主流程
        }
    };

    return { refresh };
}
