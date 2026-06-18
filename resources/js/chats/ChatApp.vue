<template>
    <div class="card flex h-[calc(100vh-8rem)] overflow-hidden p-0">
        <!-- 左欄：對話列表 -->
        <div class="w-72 border-r border-slate-200 flex flex-col flex-shrink-0">
            <div class="p-3 border-b border-slate-200">
                <select class="form-control" @change="onUserSelectChange">
                    <option value="">＋ 開新對話…</option>
                    <option v-for="u in selectableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </div>

            <!-- 列表載入骨架 -->
            <div v-show="listLoading" class="p-3 space-y-3">
                <div v-for="i in 6" :key="i" class="flex items-center gap-2.5">
                    <div class="chat-skeleton w-9 h-9 rounded-full"></div>
                    <div class="flex-1 space-y-1.5">
                        <div class="chat-skeleton h-3 w-2/3"></div>
                        <div class="chat-skeleton h-2.5 w-1/2"></div>
                    </div>
                </div>
            </div>

            <!-- 列表空狀態 -->
            <div
                data-convo-empty
                v-show="!listLoading && !listError && conversations.length === 0"
                class="flex-1 flex flex-col items-center justify-center px-6 text-center"
            >
                <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                </svg>
                <p class="mt-2 text-sm text-slate-400">還沒有任何對話</p>
                <p class="text-xs text-slate-400">從上方「開新對話」開始</p>
            </div>

            <!-- 列表載入失敗狀態 -->
            <div
                data-convo-error
                v-show="listError"
                class="flex-1 flex flex-col items-center justify-center px-6 text-center"
            >
                <p class="text-sm text-slate-500">對話載入失敗</p>
                <button type="button" class="btn-primary mt-3" @click="retryLoadConversations">重新載入</button>
            </div>

            <!-- 對話清單 -->
            <ul v-show="!listLoading && !listError && conversations.length > 0" class="flex-1 overflow-y-auto">
                <li v-for="c in conversations" :key="c.id">
                    <button
                        type="button"
                        :data-convo-id="c.id"
                        :data-other-id="c.other_user.id"
                        :class="['chat-convo-item', c.id === activeId ? 'chat-convo-item-active' : '']"
                        @click="openConversation(c.id, c.other_user.id, c.other_user.name)"
                    >
                        <span class="relative flex-shrink-0">
                            <span class="chat-avatar chat-avatar-sm">{{ initials(c.other_user.name) }}</span>
                            <span
                                v-show="onlineUsers.has(Number(c.other_user.id))"
                                class="chat-online-dot absolute -bottom-0.5 -right-0.5"
                            ></span>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-slate-700 truncate">{{ c.other_user.name }}</span>
                                <span class="text-[11px] text-slate-400 flex-shrink-0">{{ c.last_message_at ? formatListTime(c.last_message_at) : '' }}</span>
                            </span>
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-xs text-slate-400 truncate">{{ c.last_message || '' }}</span>
                                <span v-show="c.unread_count > 0" class="chat-badge">{{ c.unread_count }}</span>
                            </span>
                        </span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- 右欄：訊息串 -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- 對話 header -->
            <div v-show="activeId !== null" class="h-14 border-b border-slate-200 flex items-center px-4 gap-3">
                <span class="relative">
                    <span class="chat-avatar chat-avatar-sm">{{ initials(activeOtherName) }}</span>
                    <span
                        v-show="activeOtherId !== null && onlineUsers.has(activeOtherId)"
                        class="chat-online-dot absolute -bottom-0.5 -right-0.5"
                    ></span>
                </span>
                <span class="font-medium text-slate-700 truncate">{{ activeOtherName }}</span>
            </div>

            <!-- 未選對話空狀態 -->
            <div
                v-show="activeId === null"
                class="flex-1 flex flex-col items-center justify-center text-center px-6"
            >
                <svg class="w-14 h-14 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
                <p class="mt-3 text-sm text-slate-400">選擇一個對話開始聊天</p>
            </div>

            <!-- 訊息載入骨架 -->
            <div v-show="threadLoading" class="flex-1 p-4 space-y-4 bg-slate-50">
                <div class="flex justify-start"><div class="chat-skeleton h-10 w-48 rounded-2xl"></div></div>
                <div class="flex justify-end"><div class="chat-skeleton h-8 w-40 rounded-2xl"></div></div>
                <div class="flex justify-start"><div class="chat-skeleton h-12 w-56 rounded-2xl"></div></div>
                <div class="flex justify-end"><div class="chat-skeleton h-8 w-32 rounded-2xl"></div></div>
            </div>

            <!-- 訊息載入失敗狀態 -->
            <div
                data-thread-error
                v-show="threadError"
                class="flex-1 flex flex-col items-center justify-center text-center px-6"
            >
                <p class="text-sm text-slate-500">訊息載入失敗</p>
                <button type="button" class="btn-primary mt-3" @click="retryOpenConversation">重新載入</button>
            </div>

            <!-- 訊息串（含跳到最新 pill） -->
            <div
                data-thread-wrap
                v-show="threadWrap"
                class="relative flex-1 min-h-0"
            >
                <div
                    ref="threadEl"
                    class="absolute inset-0 overflow-y-auto p-4 bg-slate-50"
                    @scroll="onThreadScroll"
                >
                    <!-- 日期分隔線 + 訊息泡泡 -->
                    <template v-for="item in messages" :key="item.key">
                        <!-- 日期分隔 -->
                        <div v-if="item.type === 'divider'" class="flex justify-center my-3">
                            <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-500 text-[11px]">{{ item.label }}</span>
                        </div>
                        <!-- 訊息泡泡 -->
                        <div
                            v-else
                            :class="['flex items-end gap-2', item.mine ? 'justify-end' : 'justify-start', item.grouped ? 'mt-0.5' : 'mt-3']"
                        >
                            <!-- 對方頭像（非 grouped 才顯示）-->
                            <template v-if="!item.mine">
                                <span v-if="item.grouped" class="w-8 flex-shrink-0"></span>
                                <span v-else class="chat-avatar chat-avatar-sm">{{ initials(activeOtherName) }}</span>
                            </template>
                            <div :class="['flex flex-col min-w-0 max-w-[70%]', item.mine ? 'items-end' : 'items-start']">
                                <div :class="item.mine ? 'chat-bubble chat-bubble-out' : 'chat-bubble chat-bubble-in'">{{ item.body }}</div>
                                <div class="chat-bubble-time">
                                    <span>{{ item.time }}</span>
                                    <!-- 樂觀送出狀態 -->
                                    <template v-if="item.pendingStatus">
                                        <span
                                            v-if="item.pendingStatus === 'sending'"
                                            class="chat-status ml-1 text-slate-400"
                                        >· 傳送中…</span>
                                        <span
                                            v-else-if="item.pendingStatus === 'sent'"
                                            class="chat-status ml-1 text-slate-400"
                                        >· 已送出</span>
                                        <span
                                            v-else-if="item.pendingStatus === 'failed'"
                                            class="chat-status ml-1 text-red-500"
                                        >· 傳送失敗 <button type="button" class="underline cursor-pointer" @click="item.retry && item.retry()">重試</button></span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- 對話無訊息空狀態 -->
                <div
                    v-show="emptyMessages"
                    class="absolute inset-0 flex flex-col items-center justify-center text-center px-6 pointer-events-none"
                >
                    <p class="text-sm text-slate-400">尚無訊息</p>
                    <p class="text-xs text-slate-400">送出第一則訊息開始對話</p>
                </div>

                <!-- 跳到最新 pill -->
                <button
                    v-show="scrollPill"
                    type="button"
                    class="chat-scroll-pill"
                    @click="forceScrollBottom"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                    新訊息
                </button>
            </div>

            <!-- 輸入中指示器 -->
            <div v-show="typing" class="px-4 py-1.5 flex items-center gap-2 text-xs text-slate-400">
                <span class="chat-typing-dots"><span></span><span></span><span></span></span>
                對方正在輸入…
            </div>

            <!-- 訊息表單 -->
            <form
                id="message-form"
                v-show="threadWrap"
                class="border-t border-slate-200 p-3 flex gap-2"
                @submit.prevent="onFormSubmit"
            >
                <input
                    id="message-input"
                    ref="inputEl"
                    type="text"
                    autocomplete="off"
                    class="form-control flex-1"
                    placeholder="輸入訊息…"
                    v-model="inputValue"
                    @input="onInputTyping"
                />
                <button type="submit" class="btn-primary">送出</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import http from '@/lib/http';
import { initials, formatListTime, formatDayLabel, formatTime, dayKey } from '@/chats/chatFormat';
import { useChatBadge } from '@/composables/useChatBadge';
import { useEcho } from '@/composables/useEcho';

// ── Props ───────────────────────────────────────────────────────
const props = defineProps({
    meId: { type: Number, required: true },
    selectableUsers: { type: Array, default: () => [] },
});

// ── Composables ──────────────────────────────────────────────────
const { refresh: refreshChatBadge } = useChatBadge();
const { echo } = useEcho();

// ── State ────────────────────────────────────────────────────────
const conversations = ref([]);
const activeId = ref(null);
const activeOtherId = ref(null);
const activeOtherName = ref('');

/** Rendered message list: items are either { type:'divider', key, label }
 *  or { type:'msg', key, id, mine, grouped, body, time, pendingStatus?, retry? } */
const messages = ref([]);

/**
 * Set of online user IDs — stored in a ref so Vue tracks the reference.
 * We replace with a new Set on each mutation to guarantee reactivity in all environments.
 */
const onlineUsers = ref(new Set());

/** Dedup set for rendered message IDs */
const renderedMessageIds = new Set();

/** Pending optimistic sends */
const pendingSends = [];

// List panel states
const listLoading = ref(true);
const listError = ref(false);

// Thread panel states
const threadLoading = ref(false);
const threadError = ref(false);
const threadWrap = ref(false);
const emptyMessages = ref(false);

// UI states
const scrollPill = ref(false);
const typing = ref(false);

// Inputs
const inputValue = ref('');

// Template refs
const threadEl = ref(null);
const inputEl = ref(null);

// Timers (cleaned up on unmount)
let hideTypingTimer = null;
let listReloadTimer = null;

// Rendering trackers (reset on each openConversation)
let lastRenderedDateKey = null;
let lastRenderedSenderId = null;

// Echo channel tracking
let conversationChannelId = null;

// ── Helpers ─────────────────────────────────────────────────────
const isNearBottom = () => {
    if (!threadEl.value) { return true; }
    return threadEl.value.scrollHeight - threadEl.value.scrollTop - threadEl.value.clientHeight < 80;
};

const forceScrollBottom = () => {
    if (threadEl.value) {
        threadEl.value.scrollTop = threadEl.value.scrollHeight;
    }
    scrollPill.value = false;
};

const onThreadScroll = () => {
    if (isNearBottom()) {
        scrollPill.value = false;
    }
};

// ── Conversation list ────────────────────────────────────────────
const loadConversations = async () => {
    try {
        const { data } = await http.get('/chats/conversations');
        listLoading.value = false;
        listError.value = false;
        conversations.value = data.conversations;
    } catch {
        listLoading.value = false;
        // If list was already populated keep it; only show error on initial empty state
        if (conversations.value.length === 0) {
            listError.value = true;
        }
        // else: silently keep existing list (background reload failure)
    }
};

const retryLoadConversations = () => {
    listError.value = false;
    listLoading.value = true;
    loadConversations();
};

/** Debounced list reload — called after send/receive */
const scheduleListReload = () => {
    clearTimeout(listReloadTimer);
    listReloadTimer = setTimeout(loadConversations, 300);
};

// ── Message rendering ────────────────────────────────────────────
/**
 * Append a message (or a date divider + message) to the `messages` ref array.
 *
 * @param {object} msg  - { id?, sender_id, body, created_at? }
 * @param {object} opts - { live?: boolean }
 */
const appendMessage = (msg, { live = false } = {}) => {
    // Dedup by message id
    if (msg.id != null) {
        if (renderedMessageIds.has(msg.id)) { return; }

        // 自身送出的廣播：認領對應的樂觀泡泡(以 body 依序配對),沿用該列不另渲染。
        if (Number(msg.sender_id) === props.meId) {
            const pending = pendingSends.find((p) => !p.settled && p.body === msg.body);
            if (pending) {
                renderedMessageIds.add(msg.id);
                settle(pending, 'sent');
                return;
            }
        }
        renderedMessageIds.add(msg.id);
    }

    emptyMessages.value = false;

    const near = isNearBottom();
    const date = msg.created_at ? new Date(msg.created_at) : new Date();
    const key = dayKey(date);

    // Date divider
    if (key !== lastRenderedDateKey) {
        lastRenderedDateKey = key;
        lastRenderedSenderId = null;
        messages.value.push({
            type: 'divider',
            key: `divider-${key}`,
            label: formatDayLabel(date),
        });
    }

    const mine = Number(msg.sender_id) === props.meId;
    const grouped = lastRenderedSenderId === Number(msg.sender_id);
    lastRenderedSenderId = Number(msg.sender_id);

    messages.value.push({
        type: 'msg',
        key: `msg-${msg.id ?? Date.now()}-${messages.value.length}`,
        id: msg.id,
        mine,
        grouped,
        body: msg.body,
        time: formatTime(date),
    });

    if (near) {
        forceScrollBottom();
    } else if (live && !mine) {
        scrollPill.value = true;
    }
};

// ── Optimistic send ──────────────────────────────────────────────
/**
 * 樂觀送出的單一收斂點：POST 成功或自身廣播認領皆可結算。
 */
const settle = (pending, status, retry) => {
    if (pending.settled) { return; }
    pending.settled = true;
    const i = pendingSends.indexOf(pending);
    if (i !== -1) { pendingSends.splice(i, 1); }
    // 透過 pendingKey 在 reactive messages 陣列中找到對應項目並更新
    const msgItem = messages.value.find((m) => m._pendingKey === pending.pendingKey);
    if (msgItem) {
        msgItem.pendingStatus = status;
        if (status === 'failed') {
            msgItem.retry = retry;
        }
    }
};

/**
 * 送出訊息：樂觀插入 → POST → 成功 settle('sent') / 失敗 settle('failed', retry)
 */
const sendBody = async (body) => {
    const targetId = activeId.value;
    if (!body || !targetId) { return; }

    emptyMessages.value = false;

    const date = new Date();
    const key = dayKey(date);

    // 日期分隔
    if (key !== lastRenderedDateKey) {
        lastRenderedDateKey = key;
        lastRenderedSenderId = null;
        messages.value.push({
            type: 'divider',
            key: `divider-${key}`,
            label: formatDayLabel(date),
        });
    }

    const grouped = lastRenderedSenderId === props.meId;
    lastRenderedSenderId = props.meId;

    const pendingKey = `pending-${Date.now()}-${messages.value.length}`;

    messages.value.push({
        type: 'msg',
        key: pendingKey,
        id: null,
        mine: true,
        grouped,
        body,
        time: formatTime(date),
        pendingStatus: 'sending',
        retry: null,
        _pendingKey: pendingKey,
    });

    const pending = { body, settled: false, pendingKey };
    pendingSends.push(pending);

    forceScrollBottom();

    try {
        const { data } = await http.post(`/chats/${targetId}/messages`, { body });
        const id = data.message?.id;
        if (id != null) {
            renderedMessageIds.add(id);
        }
        settle(pending, 'sent');
        scheduleListReload();
    } catch {
        if (pending.settled) { return; }
        settle(pending, 'failed', () => {
            const idx = messages.value.findIndex((m) => m._pendingKey === pending.pendingKey);
            if (idx !== -1) { messages.value.splice(idx, 1); }
            sendBody(body);
        });
    }
};

// ── Echo: subscribe to conversation channel ──────────────────────
/**
 * Subscribe to the private conversation channel for typing whispers.
 * Leaves the previous channel if switching conversations.
 * No-op if echo is null.
 */
const subscribeConversationChannel = (id) => {
    if (!echo || conversationChannelId === id) { return; }
    if (conversationChannelId) {
        echo.leave(`chat.conversation.${conversationChannelId}`);
    }
    conversationChannelId = id;
    echo.private(`chat.conversation.${id}`).listenForWhisper('typing', () => {
        typing.value = true;
        clearTimeout(hideTypingTimer);
        hideTypingTimer = setTimeout(() => { typing.value = false; }, 2000);
    });
};

// ── Open conversation ─────────────────────────────────────────────
const openConversation = async (id, otherId, otherName) => {
    activeId.value = id;
    activeOtherId.value = Number(otherId);
    activeOtherName.value = otherName;

    messages.value = [];
    renderedMessageIds.clear();
    lastRenderedDateKey = null;
    lastRenderedSenderId = null;
    scrollPill.value = false;

    threadLoading.value = true;
    threadError.value = false;
    threadWrap.value = false;
    emptyMessages.value = false;
    typing.value = false;

    let loadedMessages = null;
    try {
        const { data } = await http.get(`/chats/${id}/messages`);
        loadedMessages = data.messages;
    } catch {
        loadedMessages = null;
    }

    // User switched conversation while loading — abandon this render
    if (activeId.value !== id) { return; }

    threadLoading.value = false;

    if (loadedMessages == null) {
        threadError.value = true;
        threadWrap.value = false;
        return;
    }

    threadWrap.value = true;

    loadedMessages.slice().reverse().forEach((m) => appendMessage(m));
    emptyMessages.value = messages.value.length === 0;

    await nextTick();
    forceScrollBottom();

    // Subscribe to conversation channel for typing indicators
    subscribeConversationChannel(id);

    if (inputEl.value) { inputEl.value.focus(); }

    try {
        await http.patch(`/chats/${id}/read`);
        refreshChatBadge();
    } catch {
        // non-critical
    }

    await loadConversations();
};

const retryOpenConversation = () => {
    if (activeId.value !== null) {
        openConversation(activeId.value, activeOtherId.value, activeOtherName.value);
    }
};

// ── Form ─────────────────────────────────────────────────────────
const onFormSubmit = () => {
    if (!activeId.value) { return; }
    const body = inputValue.value.trim();
    if (!body) { return; }
    inputValue.value = '';
    sendBody(body);
};

// ── Typing whisper ────────────────────────────────────────────────
const onInputTyping = () => {
    if (!activeId.value || !echo) { return; }
    echo.private(`chat.conversation.${activeId.value}`).whisper('typing', { from: props.meId });
};

// ── User select: start new conversation ──────────────────────────
const onUserSelectChange = async (e) => {
    const targetId = e.target.value;
    if (!targetId) { return; }
    const option = e.target.options[e.target.selectedIndex];
    const name = option ? option.textContent.trim() : '';
    e.target.value = ''; // reset select

    try {
        const { data } = await http.post('/chats/start', { target_user_id: Number(targetId) });
        await loadConversations();
        openConversation(data.conversation_id, Number(targetId), name);
    } catch {
        // non-critical; user can retry
    }
};

// ── window chat:message listener ─────────────────────────────────
const onChatMessage = (e) => {
    const msg = e.detail;
    if (Number(msg.conversation_id) === activeId.value) {
        appendMessage(msg, { live: true });
        http.patch(`/chats/${activeId.value}/read`).then(() => refreshChatBadge()).catch(() => {});
    }
    scheduleListReload();
};

// ── Lifecycle ────────────────────────────────────────────────────
onMounted(() => {
    loadConversations();

    // Register window listener for incoming messages (from bootstrap.js global subscription)
    window.addEventListener('chat:message', onChatMessage);

    // Presence: online status — gracefully skipped when echo is null
    if (echo) {
        echo.join('chat.online')
            .here((users) => {
                const next = new Set(onlineUsers.value);
                users.forEach((u) => next.add(Number(u.id)));
                onlineUsers.value = next;
            })
            .joining((u) => {
                const next = new Set(onlineUsers.value);
                next.add(Number(u.id));
                onlineUsers.value = next;
            })
            .leaving((u) => {
                const next = new Set(onlineUsers.value);
                next.delete(Number(u.id));
                onlineUsers.value = next;
            });
    }
});

onBeforeUnmount(() => {
    clearTimeout(hideTypingTimer);
    clearTimeout(listReloadTimer);
    window.removeEventListener('chat:message', onChatMessage);

    if (echo) {
        echo.leave('chat.online');
        if (conversationChannelId) {
            echo.leave(`chat.conversation.${conversationChannelId}`);
        }
    }
});

// Expose for tests and integration
defineExpose({
    loadConversations,
    openConversation,
    appendMessage,
    sendBody,
    settle,
    scheduleListReload,
    conversations,
    messages,
    activeId,
    onlineUsers,
    pendingSends,
    renderedMessageIds,
    typing,
    scrollPill,
});
</script>
