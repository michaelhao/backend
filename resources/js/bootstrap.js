import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// 全站未讀 badge：一律向伺服器抓「權威」數字，避免本地累加在多分頁/競態下飄掉。
// 單一實作供 bootstrap 與聊天頁共用，不在兩處各寫一份。
window.refreshChatBadge = () => {
    const badge = document.getElementById('chat-unread-badge');
    if (!badge) {
        return;
    }
    window.axios
        .get('/chats/unread-count')
        .then(({ data }) => {
            badge.textContent = data.unread_count;
            badge.classList.toggle('hidden', data.unread_count <= 0);
        })
        .catch(() => {});
};

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const userIdMeta = document.querySelector('meta[name="user-id"]');

if (reverbKey && userIdMeta) {
    window.currentUserId = Number(userIdMeta.content);

    // 空字串不會被 ?? 接住，Number('')=0 會產生壞掉的 ws port；用 || 確保非零
    const reverbPort = Number(import.meta.env.VITE_REVERB_PORT) || 8080;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo.private(`chat.user.${window.currentUserId}`).listen('.message.sent', (event) => {
        // 廣播給聊天頁處理（若該頁開著）
        window.dispatchEvent(new CustomEvent('chat:message', { detail: event }));
        // 重新抓權威未讀數（自己送的訊息不計入未讀，故數字自然正確）
        window.refreshChatBadge();
    });
}

// 進入任一後台頁面即更新一次 badge（不論有無 Echo）
window.refreshChatBadge();
