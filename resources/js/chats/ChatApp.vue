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
                                    <!-- status slot for task 4.4 optimistic send -->
                                    <span v-if="item.statusKey" :data-status-key="item.statusKey" class="chat-status ml-1"></span>
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

            <!-- 訊息表單 (stub: task 4.4 implements send) -->
            <form
                v-show="threadWrap"
                class="border-t border-slate-200 p-3 flex gap-2"
                @submit.prevent="onFormSubmit"
            >
                <input
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
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
import http from '@/lib/http';
import { initials, formatListTime, formatDayLabel, formatTime, dayKey } from '@/chats/chatFormat';
import { useChatBadge } from '@/composables/useChatBadge';

// ── Props ───────────────────────────────────────────────────────
const props = defineProps({
    meId: { type: Number, required: true },
});

// ── Composables ──────────────────────────────────────────────────
const { refresh: refreshChatBadge } = useChatBadge();

// ── State ────────────────────────────────────────────────────────
const conversations = ref([]);
const activeId = ref(null);
const activeOtherId = ref(null);
const activeOtherName = ref('');

/** Rendered message list: items are either { type:'divider', key, label }
 *  or { type:'msg', key, id, mine, grouped, body, time, statusKey? } */
const messages = ref([]);

/** Set of online user IDs — populated by task 4.5 realtime subscription */
const onlineUsers = reactive(new Set());

/** Dedup set for rendered message IDs — reused by task 4.4 optimistic send */
const renderedMessageIds = new Set();

/** Pending optimistic sends — populated/settled by task 4.4 */
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

// Selectable users for the "start new conversation" select (task 4.5 populates this via props/fetch)
const selectableUsers = ref([]);

// Timers (cleaned up on unmount)
let hideTypingTimer = null;
let listReloadTimer = null;

// Rendering trackers (reset on each openConversation)
let lastRenderedDateKey = null;
let lastRenderedSenderId = null;

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

/** Debounced list reload — used by task 4.4/4.5 after send/receive */
const scheduleListReload = () => {
    clearTimeout(listReloadTimer);
    listReloadTimer = setTimeout(loadConversations, 300);
};

// ── Message rendering ────────────────────────────────────────────
/**
 * Append a message (or a date divider + message) to the `messages` ref array.
 * Mirrors appendMessage from index.js but works with the reactive array.
 *
 * @param {object} msg  - { id?, sender_id, body, created_at? }
 * @param {object} opts - { live?: boolean }  (live = realtime; deferred to task 4.5)
 */
const appendMessage = (msg, { live = false } = {}) => {
    // Dedup by message id
    if (msg.id != null) {
        if (renderedMessageIds.has(msg.id)) { return; }

        // Task 4.4: self-sent broadcast claim against pendingSends (stub hook)
        if (Number(msg.sender_id) === props.meId) {
            const pending = pendingSends.find((p) => !p.settled && p.body === msg.body);
            if (pending) {
                renderedMessageIds.add(msg.id);
                // settle(pending, 'sent') — implemented in task 4.4
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

// ── Open conversation ─────────────────────────────────────────────
const openConversation = async (id, otherId, otherName) => {
    // Set active state immediately
    activeId.value = id;
    activeOtherId.value = Number(otherId);
    activeOtherName.value = otherName;

    // Reset thread state
    messages.value = [];
    renderedMessageIds.clear();
    lastRenderedDateKey = null;
    lastRenderedSenderId = null;
    scrollPill.value = false;

    // Show skeleton
    threadLoading.value = true;
    threadError.value = false;
    threadWrap.value = false;
    emptyMessages.value = false;
    typing.value = false;

    // Load messages
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

    // Failure — show error, do NOT treat as empty conversation
    if (loadedMessages === null) {
        threadError.value = true;
        threadWrap.value = false;
        return;
    }

    threadWrap.value = true;

    // Render messages in chronological order (API returns newest-first, so reverse)
    loadedMessages.slice().reverse().forEach((m) => appendMessage(m));

    // Determine empty state from what was actually rendered
    emptyMessages.value = messages.value.length === 0;

    // Scroll to bottom after render
    await nextTick(); // wait for DOM to flush
    forceScrollBottom();

    // Task 4.5: subscribeConversationChannel(id)

    // Focus input
    if (inputEl.value) { inputEl.value.focus(); }

    // Mark as read + refresh badge
    try {
        await http.patch(`/chats/${id}/read`);
        refreshChatBadge();
    } catch {
        // non-critical
    }

    // Reload conversation list to clear unread badge in sidebar
    await loadConversations();
};

const retryOpenConversation = () => {
    if (activeId.value !== null) {
        openConversation(activeId.value, activeOtherId.value, activeOtherName.value);
    }
};

// ── Form (stub — task 4.4 implements send) ───────────────────────
const onFormSubmit = () => {
    // Stub: task 4.4 will implement sendBody(inputValue.value.trim())
};

// ── Typing whisper (stub — task 4.5 wires Echo) ──────────────────
const onInputTyping = () => {
    // Stub: task 4.5 will whisper typing event via Echo
};

// ── User select (stub — task 4.5 implements POST /chats/start) ───
const onUserSelectChange = async (e) => {
    const targetId = e.target.value;
    if (!targetId) { return; }
    // Stub: task 4.5 will POST /chats/start and call openConversation
    e.target.value = '';
};

// ── Lifecycle ────────────────────────────────────────────────────
onMounted(() => {
    loadConversations();
});

onBeforeUnmount(() => {
    clearTimeout(hideTypingTimer);
    clearTimeout(listReloadTimer);
    // Task 4.5: leave Echo channels here
});

// Expose for tests and task 4.4/4.5 integration
defineExpose({
    loadConversations,
    openConversation,
    appendMessage,
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
