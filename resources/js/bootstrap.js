import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const userIdMeta = document.querySelector('meta[name="user-id"]');

if (reverbKey && userIdMeta) {
    window.currentUserId = Number(userIdMeta.content);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    initChatBadge();
}

function initChatBadge() {
    const badge = document.getElementById('chat-unread-badge');
    if (!badge) {
        return;
    }

    const setBadge = (count) => {
        badge.textContent = count;
        badge.classList.toggle('hidden', count <= 0);
    };

    window.axios
        .get('/chats/unread-count')
        .then(({ data }) => setBadge(data.unread_count))
        .catch(() => {});

    window.Echo.private(`chat.user.${window.currentUserId}`).listen('.message.sent', (event) => {
        // 廣播給聊天頁處理（若該頁開著）
        window.dispatchEvent(new CustomEvent('chat:message', { detail: event }));

        // 若使用者沒在看這個對話，未讀 +1
        if (window.chatActiveConversationId !== event.conversation_id) {
            const current = parseInt(badge.textContent || '0', 10) || 0;
            setBadge(current + 1);
        }
    });
}
