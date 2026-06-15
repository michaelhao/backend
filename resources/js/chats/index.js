const root = document.getElementById('chat-app');

if (root) {
    const meId = Number(root.dataset.userId);

    const els = {
        list: document.getElementById('conversation-list'),
        convoSkeleton: document.getElementById('convo-skeleton'),
        convoEmpty: document.getElementById('convo-empty'),
        thread: document.getElementById('message-thread'),
        threadWrap: document.getElementById('thread-wrap'),
        threadSkeleton: document.getElementById('thread-skeleton'),
        emptyNone: document.getElementById('chat-empty-none'),
        emptyMessages: document.getElementById('chat-empty-messages'),
        scrollPill: document.getElementById('scroll-to-latest'),
        form: document.getElementById('message-form'),
        input: document.getElementById('message-input'),
        typing: document.getElementById('typing-indicator'),
        header: document.getElementById('chat-header'),
        title: document.getElementById('chat-title'),
        headerAvatar: document.getElementById('chat-header-avatar'),
        onlineDot: document.getElementById('chat-online-dot'),
        userSelect: document.getElementById('start-user-select'),
    };

    let activeId = null;
    let activeOtherId = null;
    let activeOtherName = '';
    let conversationChannelId = null;
    let hideTypingTimer = null;
    let listReloadTimer = null;
    let lastRenderedDate = null;
    let lastRenderedSenderId = null;
    const onlineUsers = new Set();
    const renderedMessageIds = new Set();

    // ── 格式化工具（zh-TW，零外部套件）────────────────────────────
    const timeFmt = new Intl.DateTimeFormat('zh-TW', { hour: '2-digit', minute: '2-digit', hour12: false });
    const fullDateFmt = new Intl.DateTimeFormat('zh-TW', { year: 'numeric', month: 'long', day: 'numeric' });
    const shortDateFmt = new Intl.DateTimeFormat('zh-TW', { month: 'numeric', day: 'numeric' });

    const dayKey = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
    const isSameDay = (a, b) => dayKey(a) === dayKey(b);
    const yesterdayOf = (now) => {
        const y = new Date(now);
        y.setDate(now.getDate() - 1);
        return y;
    };

    const formatTime = (d) => timeFmt.format(d);

    const formatDayLabel = (d) => {
        const now = new Date();
        if (isSameDay(d, now)) {
            return '今天';
        }
        if (isSameDay(d, yesterdayOf(now))) {
            return '昨天';
        }
        return fullDateFmt.format(d);
    };

    const formatListTime = (iso) => {
        const d = new Date(iso);
        const now = new Date();
        if (isSameDay(d, now)) {
            return formatTime(d);
        }
        if (isSameDay(d, yesterdayOf(now))) {
            return '昨天';
        }
        return shortDateFmt.format(d);
    };

    const initials = (name) => {
        const s = (name || '').trim();
        if (!s) {
            return '?';
        }
        const parts = s.split(/\s+/);
        if (parts.length >= 2) {
            return (Array.from(parts[0])[0] + Array.from(parts[1])[0]).toUpperCase();
        }
        return Array.from(s)[0];
    };

    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    };

    // ── 線上狀態 ─────────────────────────────────────────────────
    const updateOnlineDot = () => {
        els.onlineDot.classList.toggle('hidden', !(activeOtherId && onlineUsers.has(activeOtherId)));
    };

    const updateListOnlineDots = () => {
        els.list.querySelectorAll('[data-other-id]').forEach((btn) => {
            const dot = btn.querySelector('[data-online-dot]');
            if (dot) {
                dot.classList.toggle('hidden', !onlineUsers.has(Number(btn.dataset.otherId)));
            }
        });
    };

    const updateActiveHighlight = () => {
        els.list.querySelectorAll('[data-convo-id]').forEach((btn) => {
            btn.classList.toggle('chat-convo-item-active', Number(btn.dataset.convoId) === activeId);
        });
    };

    // ── 對話列表 ─────────────────────────────────────────────────
    const renderConversations = (conversations) => {
        els.list.innerHTML = '';

        if (conversations.length === 0) {
            els.list.classList.add('hidden');
            els.convoEmpty.classList.remove('hidden');
            return;
        }
        els.convoEmpty.classList.add('hidden');
        els.list.classList.remove('hidden');

        conversations.forEach((c) => {
            const online = onlineUsers.has(Number(c.other_user.id));
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.convoId = c.id;
            btn.dataset.otherId = c.other_user.id;
            btn.className = `chat-convo-item${c.id === activeId ? ' chat-convo-item-active' : ''}`;
            btn.innerHTML = `
                <span class="relative flex-shrink-0">
                    <span class="chat-avatar chat-avatar-sm">${escapeHtml(initials(c.other_user.name))}</span>
                    <span data-online-dot class="${online ? '' : 'hidden'} chat-online-dot absolute -bottom-0.5 -right-0.5"></span>
                </span>
                <span class="flex-1 min-w-0">
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium text-slate-700 truncate">${escapeHtml(c.other_user.name)}</span>
                        <span class="text-[11px] text-slate-400 flex-shrink-0">${c.last_message_at ? escapeHtml(formatListTime(c.last_message_at)) : ''}</span>
                    </span>
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-400 truncate">${escapeHtml(c.last_message || '')}</span>
                        <span class="${c.unread_count > 0 ? '' : 'hidden'} chat-badge">${c.unread_count}</span>
                    </span>
                </span>
            `;
            btn.addEventListener('click', () => openConversation(c.id, c.other_user.id, c.other_user.name));
            li.appendChild(btn);
            els.list.appendChild(li);
        });
    };

    const loadConversations = () =>
        window.axios.get('/chats/conversations').then(({ data }) => {
            els.convoSkeleton.classList.add('hidden');
            renderConversations(data.conversations);
        });

    // 收到多則訊息時把列表重抓收斂成一次，避免每則訊息都整包重載
    const scheduleListReload = () => {
        clearTimeout(listReloadTimer);
        listReloadTimer = setTimeout(loadConversations, 300);
    };

    // ── 訊息串 ───────────────────────────────────────────────────
    const isNearBottom = () => els.thread.scrollHeight - els.thread.scrollTop - els.thread.clientHeight < 80;

    const forceScrollBottom = () => {
        els.thread.scrollTop = els.thread.scrollHeight;
        els.scrollPill.classList.add('hidden');
    };

    const appendDateDividerIfNeeded = (date) => {
        const key = dayKey(date);
        if (key === lastRenderedDate) {
            return;
        }
        lastRenderedDate = key;
        lastRenderedSenderId = null; // 跨日中斷分組
        const div = document.createElement('div');
        div.className = 'flex justify-center my-3';
        div.innerHTML = `<span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-500 text-[11px]">${escapeHtml(formatDayLabel(date))}</span>`;
        els.thread.appendChild(div);
    };

    const buildMessageRow = ({ mine, grouped, otherName, body, time, withStatus }) => {
        const row = document.createElement('div');
        row.className = `flex items-end gap-2 ${mine ? 'justify-end' : 'justify-start'} ${grouped ? 'mt-0.5' : 'mt-3'}`;

        const col = document.createElement('div');
        col.className = `flex flex-col min-w-0 max-w-[70%] ${mine ? 'items-end' : 'items-start'}`;

        const bubble = document.createElement('div');
        bubble.className = mine ? 'chat-bubble chat-bubble-out' : 'chat-bubble chat-bubble-in';
        bubble.textContent = body;

        const meta = document.createElement('div');
        meta.className = 'chat-bubble-time';
        const timeSpan = document.createElement('span');
        timeSpan.textContent = time;
        meta.appendChild(timeSpan);
        if (withStatus) {
            const status = document.createElement('span');
            status.className = 'chat-status ml-1';
            meta.appendChild(status);
        }

        col.appendChild(bubble);
        col.appendChild(meta);

        if (mine) {
            row.appendChild(col);
        } else {
            const avatar = document.createElement('span');
            if (grouped) {
                avatar.className = 'w-8 flex-shrink-0';
            } else {
                avatar.className = 'chat-avatar chat-avatar-sm';
                avatar.textContent = initials(otherName);
            }
            row.appendChild(avatar);
            row.appendChild(col);
        }
        return row;
    };

    const appendMessage = (msg) => {
        // 以訊息 id 去重：本地送出已 append 的訊息，廣播回來時不重複顯示
        if (msg.id != null) {
            if (renderedMessageIds.has(msg.id)) {
                return;
            }
            renderedMessageIds.add(msg.id);
        }
        els.emptyMessages.classList.add('hidden');

        const near = isNearBottom();
        const date = msg.created_at ? new Date(msg.created_at) : new Date();
        appendDateDividerIfNeeded(date);

        const mine = Number(msg.sender_id) === meId;
        const grouped = lastRenderedSenderId === Number(msg.sender_id);
        lastRenderedSenderId = Number(msg.sender_id);

        const row = buildMessageRow({
            mine,
            grouped,
            otherName: activeOtherName,
            body: msg.body,
            time: formatTime(date),
        });
        els.thread.appendChild(row);

        if (near) {
            forceScrollBottom();
        } else {
            els.scrollPill.classList.remove('hidden');
        }
    };

    const setStatus = (statusEl, status, retry) => {
        if (!statusEl) {
            return;
        }
        statusEl.innerHTML = '';
        statusEl.classList.toggle('text-red-500', status === 'failed');
        statusEl.classList.toggle('text-slate-400', status !== 'failed');
        if (status === 'sending') {
            statusEl.textContent = '· 傳送中…';
        } else if (status === 'sent') {
            statusEl.textContent = '· 已送出';
        } else if (status === 'failed') {
            statusEl.append('· 傳送失敗 ');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'underline cursor-pointer';
            btn.textContent = '重試';
            btn.addEventListener('click', retry);
            statusEl.appendChild(btn);
        }
    };

    const sendBody = async (body) => {
        const targetId = activeId;
        if (!body || !targetId) {
            return;
        }

        els.emptyMessages.classList.add('hidden');
        const date = new Date();
        appendDateDividerIfNeeded(date);
        const grouped = lastRenderedSenderId === meId;
        lastRenderedSenderId = meId;

        const row = buildMessageRow({ mine: true, grouped, body, time: formatTime(date), withStatus: true });
        const statusEl = row.querySelector('.chat-status');
        setStatus(statusEl, 'sending');
        els.thread.appendChild(row);
        forceScrollBottom();

        try {
            const { data } = await window.axios.post(`/chats/${targetId}/messages`, { body });
            const id = data.message?.id;
            // 自身廣播（chat.user.{meId}）可能比 POST 回應先抵達並已渲染同一則 →
            // 此時 id 已在去重集合中，移除樂觀泡泡避免重複顯示
            if (id != null && renderedMessageIds.has(id)) {
                row.remove();
                scheduleListReload();
                return;
            }
            if (id != null) {
                renderedMessageIds.add(id); // 之後廣播回來時去重
            }
            setStatus(statusEl, 'sent');
            scheduleListReload();
        } catch (e) {
            setStatus(statusEl, 'failed', () => {
                row.remove();
                sendBody(body);
            });
        }
    };

    // ── 即時頻道（輸入中 whisper）────────────────────────────────
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

    // ── 開啟對話 ─────────────────────────────────────────────────
    const openConversation = async (id, otherId, otherName) => {
        activeId = id;
        activeOtherId = Number(otherId);
        activeOtherName = otherName;
        lastRenderedDate = null;
        lastRenderedSenderId = null;
        renderedMessageIds.clear();
        els.thread.innerHTML = '';

        els.emptyNone.classList.add('hidden');
        els.header.classList.remove('hidden');
        els.title.textContent = otherName;
        els.headerAvatar.textContent = initials(otherName);
        updateOnlineDot();
        updateActiveHighlight();

        // 載入中骨架
        els.threadWrap.classList.add('hidden');
        els.emptyMessages.classList.add('hidden');
        els.form.classList.add('hidden');
        els.typing.classList.add('hidden');
        els.threadSkeleton.classList.remove('hidden');

        let messages = [];
        try {
            const { data } = await window.axios.get(`/chats/${id}/messages`);
            messages = data.messages;
        } catch (e) {
            messages = [];
        }

        // 載入期間使用者已切換對話 → 放棄這次渲染
        if (activeId !== id) {
            return;
        }

        els.threadSkeleton.classList.add('hidden');
        els.threadWrap.classList.remove('hidden');
        els.form.classList.remove('hidden');

        messages
            .slice()
            .reverse()
            .forEach(appendMessage);
        forceScrollBottom();
        els.emptyMessages.classList.toggle('hidden', messages.length > 0);

        subscribeConversationChannel(id);
        els.input.focus();

        await window.axios.patch(`/chats/${id}/read`);
        loadConversations();
        window.refreshChatBadge?.();
    };

    // ── 事件綁定 ─────────────────────────────────────────────────
    els.form.addEventListener('submit', (e) => {
        e.preventDefault();
        const body = els.input.value.trim();
        if (!body || !activeId) {
            return;
        }
        els.input.value = '';
        sendBody(body);
    });

    els.input.addEventListener('input', () => {
        if (!activeId || !window.Echo) {
            return;
        }
        window.Echo.private(`chat.conversation.${activeId}`).whisper('typing', { from: meId });
    });

    els.thread.addEventListener('scroll', () => {
        if (isNearBottom()) {
            els.scrollPill.classList.add('hidden');
        }
    });

    els.scrollPill.addEventListener('click', forceScrollBottom);

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
                updateListOnlineDots();
            })
            .joining((u) => {
                onlineUsers.add(Number(u.id));
                updateOnlineDot();
                updateListOnlineDots();
            })
            .leaving((u) => {
                onlineUsers.delete(Number(u.id));
                updateOnlineDot();
                updateListOnlineDots();
            });
    }

    loadConversations();
}
