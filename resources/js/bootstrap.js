import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { useEcho } from '@/composables/useEcho';
import { useChatBadge } from '@/composables/useChatBadge';

// 全站未讀 badge：一律向伺服器抓「權威」數字，避免本地累加在多分頁/競態下飄掉。
window.refreshChatBadge = () => useChatBadge().refresh();

const { echo, userId } = useEcho();

if (echo) {
    echo.private(`chat.user.${userId}`).listen('.message.sent', (event) => {
        // 廣播給聊天頁處理（若該頁開著）
        window.dispatchEvent(new CustomEvent('chat:message', { detail: event }));
        // 重新抓權威未讀數（自己送的訊息不計入未讀，故數字自然正確）
        window.refreshChatBadge();
    });
}

// 進入任一後台頁面即更新一次 badge（不論有無 Echo）
window.refreshChatBadge();
