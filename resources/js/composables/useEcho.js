import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export function useEcho() {
    if (window.Echo) {
        return { echo: window.Echo, userId: window.currentUserId ?? null };
    }

    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    const userIdMeta = document.querySelector('meta[name="user-id"]');

    // userIdMeta.content 為空字串時 Number('')=0 會訂到 chat.user.0；視為未登入直接退出
    if (!reverbKey || !userIdMeta || !userIdMeta.content) {
        return { echo: null, userId: null };
    }

    const userId = Number(userIdMeta.content);
    window.currentUserId = userId;

    // 空字串不會被 ?? 接住，Number('')=0 會產生壞掉的 ws port；用 || 確保非零
    const reverbPort = Number(import.meta.env.VITE_REVERB_PORT) || 8080;

    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return { echo: window.Echo, userId };
}
