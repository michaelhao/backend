const root = document.getElementById('chat-app');

if (root) {
    const meId = Number(root.dataset.userId);

    const els = {
        list: document.getElementById('conversation-list'),
        thread: document.getElementById('message-thread'),
        form: document.getElementById('message-form'),
        input: document.getElementById('message-input'),
        typing: document.getElementById('typing-indicator'),
        title: document.getElementById('chat-title'),
        onlineDot: document.getElementById('chat-online-dot'),
        userSelect: document.getElementById('start-user-select'),
    };

    let activeId = null;
    let activeOtherId = null;
    let conversationChannelId = null;
    let hideTypingTimer = null;
    let listReloadTimer = null;
    const onlineUsers = new Set();
    const renderedMessageIds = new Set();

    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    };

    const updateOnlineDot = () => {
        els.onlineDot.classList.toggle('hidden', !(activeOtherId && onlineUsers.has(activeOtherId)));
    };

    const renderConversations = (conversations) => {
        els.list.innerHTML = '';
        conversations.forEach((c) => {
            const li = document.createElement('li');
            li.className =
                'px-3 py-2.5 border-b border-slate-100 cursor-pointer hover:bg-slate-50 flex items-center justify-between gap-2';
            li.innerHTML = `
                <div class="min-w-0">
                    <div class="text-sm font-medium text-slate-700 truncate">${escapeHtml(c.other_user.name)}</div>
                    <div class="text-xs text-slate-400 truncate">${escapeHtml(c.last_message || '')}</div>
                </div>
                <span class="${c.unread_count > 0 ? '' : 'hidden'} inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-xs">${c.unread_count}</span>
            `;
            li.addEventListener('click', () => openConversation(c.id, c.other_user.id, c.other_user.name));
            els.list.appendChild(li);
        });
    };

    const loadConversations = () =>
        window.axios.get('/chats/conversations').then(({ data }) => renderConversations(data.conversations));

    // 收到多則訊息時把列表重抓收斂成一次，避免每則訊息都整包重載
    const scheduleListReload = () => {
        clearTimeout(listReloadTimer);
        listReloadTimer = setTimeout(loadConversations, 300);
    };

    const appendMessage = (msg) => {
        // 以訊息 id 去重：本地送出已 append 的訊息，廣播回來時不重複顯示
        if (msg.id != null) {
            if (renderedMessageIds.has(msg.id)) {
                return;
            }
            renderedMessageIds.add(msg.id);
        }
        const mine = Number(msg.sender_id) === meId;
        const wrap = document.createElement('div');
        wrap.className = `flex ${mine ? 'justify-end' : 'justify-start'}`;
        wrap.innerHTML = `<div class="max-w-[70%] px-3 py-2 rounded-lg text-sm ${mine ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700'}">${escapeHtml(msg.body)}</div>`;
        els.thread.appendChild(wrap);
        els.thread.scrollTop = els.thread.scrollHeight;
    };

    const subscribeConversationChannel = (id) => {
        if (!window.Echo || conversationChannelId === id) {
            return;
        }
        if (conversationChannelId) {
            window.Echo.leave(`chat.conversation.${conversationChannelId}`);
        }
        conversationChannelId = id;
        window.Echo.private(`chat.conversation.${id}`).listenForWhisper('typing', () => {
            els.typing.classList.remove('hidden');
            clearTimeout(hideTypingTimer);
            hideTypingTimer = setTimeout(() => els.typing.classList.add('hidden'), 2000);
        });
    };

    const openConversation = async (id, otherId, otherName) => {
        activeId = id;
        activeOtherId = Number(otherId);
        els.title.textContent = otherName;
        els.form.classList.remove('hidden');
        els.typing.classList.add('hidden');
        els.thread.innerHTML = '';
        renderedMessageIds.clear();

        const { data } = await window.axios.get(`/chats/${id}/messages`);
        data.messages
            .slice()
            .reverse()
            .forEach(appendMessage);

        subscribeConversationChannel(id);
        updateOnlineDot();

        await window.axios.patch(`/chats/${id}/read`);
        loadConversations();
        window.refreshChatBadge?.();
    };

    els.form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = els.input.value.trim();
        if (!body || !activeId) {
            return;
        }
        els.input.value = '';
        const { data } = await window.axios.post(`/chats/${activeId}/messages`, { body });
        appendMessage(data.message);
        scheduleListReload();
    });

    els.input.addEventListener('input', () => {
        if (!activeId || !window.Echo) {
            return;
        }
        window.Echo.private(`chat.conversation.${activeId}`).whisper('typing', { from: meId });
    });

    els.userSelect.addEventListener('change', async () => {
        const targetId = els.userSelect.value;
        if (!targetId) {
            return;
        }
        const option = els.userSelect.options[els.userSelect.selectedIndex];
        const name = option ? option.textContent.trim() : '';
        els.userSelect.value = '';
        const { data } = await window.axios.post('/chats/start', { target_user_id: Number(targetId) });
        await loadConversations();
        openConversation(data.conversation_id, Number(targetId), name);
    });

    // 來自 bootstrap.js 全站 chat.user 訂閱的新訊息（含自己其他分頁送出的訊息）
    window.addEventListener('chat:message', (e) => {
        const msg = e.detail;
        if (Number(msg.conversation_id) === activeId) {
            appendMessage(msg); // 去重保證本分頁自送訊息不會重複
            window.axios.patch(`/chats/${activeId}/read`).then(() => window.refreshChatBadge?.());
        }
        scheduleListReload();
    });

    // 即時功能（線上狀態 / 輸入中）僅在 Echo 可用時啟用；沒設定 Reverb 時頁面仍可正常載入與送訊息
    if (window.Echo) {
        window.Echo.join('chat.online')
            .here((users) => {
                users.forEach((u) => onlineUsers.add(Number(u.id)));
                updateOnlineDot();
            })
            .joining((u) => {
                onlineUsers.add(Number(u.id));
                updateOnlineDot();
            })
            .leaving((u) => {
                onlineUsers.delete(Number(u.id));
                updateOnlineDot();
            });
    }

    loadConversations();
}
