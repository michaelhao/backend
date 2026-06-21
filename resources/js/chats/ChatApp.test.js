import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { nextTick } from 'vue';
import http from '@/lib/http';
import ChatApp from '@/chats/ChatApp.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }));
vi.mock('@/composables/useChatBadge', () => ({ useChatBadge: () => ({ refresh: vi.fn() }) }));

// Mock useEcho with a vi.fn() so individual tests can override the return value.
vi.mock('@/composables/useEcho', () => ({
    useEcho: vi.fn(() => ({ echo: null, userId: null })),
}));

// Import the mocked module so tests can control it.
import { useEcho } from '@/composables/useEcho';

// ── Echo mock factory ─────────────────────────────────────────────
function makeEchoMock() {
    const presenceCallbacks = { here: null, joining: null, leaving: null };
    const whisperCallbacks = {};
    const whisperCalls = [];
    const leftChannels = [];

    const privateChannel = {
        listenForWhisper(event, handler) {
            whisperCallbacks[event] = handler;
            return this;
        },
        whisper(event, data) {
            whisperCalls.push({ event, data });
            return this;
        },
        listen() { return this; },
    };

    const presenceChannel = {
        here(handler) { presenceCallbacks.here = handler; return this; },
        joining(handler) { presenceCallbacks.joining = handler; return this; },
        leaving(handler) { presenceCallbacks.leaving = handler; return this; },
    };

    return {
        join(channel) {
            if (channel === 'chat.online') { return presenceChannel; }
            return privateChannel;
        },
        private() { return privateChannel; },
        leave(channel) { leftChannels.push(channel); },
        // Test helpers
        _presenceCallbacks: presenceCallbacks,
        _whisperCallbacks: whisperCallbacks,
        _whisperCalls: whisperCalls,
        _leftChannels: leftChannels,
    };
}

beforeEach(() => {
    vi.resetAllMocks();
    // Default: no echo
    useEcho.mockReturnValue({ echo: null, userId: null });
    // http.patch 預設回傳 resolved promise（真實 axios 必回 promise；openConversation 會並行呼叫）
    http.patch.mockResolvedValue({});
});

// ── Helper: mount with one conversation already visible ───────────
async function mountWithConversation({ meId = 1, convoId = 1, otherId = 9, otherName = 'Bob', echo = null } = {}) {
    useEcho.mockReturnValue({ echo, userId: meId });
    http.get.mockResolvedValueOnce({
        data: {
            conversations: [
                { id: convoId, other_user: { id: otherId, name: otherName }, last_message: '', last_message_at: null, unread_count: 0 },
            ],
        },
    });
    const w = mount(ChatApp, { props: { meId } });
    await flushPromises();

    http.get.mockResolvedValueOnce({ data: { messages: [] } });
    http.patch.mockResolvedValue({});
    http.get.mockResolvedValueOnce({ data: { conversations: [] } });

    await w.get(`[data-convo-id="${convoId}"]`).trigger('click');
    await flushPromises();
    return w;
}

// ─────────────────────────────────────────────────────────────────
describe('ChatApp 列表', () => {
    it('載入並渲染對話清單', async () => {
        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: 'hi', last_message_at: null, unread_count: 2 },
                ],
            },
        });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        expect(w.text()).toContain('Bob');
        expect(w.text()).toContain('2'); // unread badge
    });

    it('清單為空顯示空狀態', async () => {
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        expect(w.get('[data-convo-empty]').isVisible()).toBe(true);
    });

    it('清單載入失敗(初次)顯示可重試錯誤', async () => {
        http.get.mockRejectedValueOnce(new Error('x'));
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        expect(w.get('[data-convo-error]').isVisible()).toBe(true);
    });

    it('背景重載失敗時保留既有列表', async () => {
        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Alice' }, last_message: 'hello', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        expect(w.text()).toContain('Alice');

        http.get.mockRejectedValueOnce(new Error('network'));
        await w.vm.loadConversations();
        await flushPromises();

        expect(w.text()).toContain('Alice');
        expect(w.get('[data-convo-error]').isVisible()).toBe(false);
    });

    it('點擊對話項目後顯示 thread-skeleton 並呼叫 GET messages', async () => {
        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 2, other_user: { id: 5, name: 'Carol' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        http.patch.mockResolvedValueOnce({});
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        const btn = w.find('[data-convo-id]');
        await btn.trigger('click');
        await flushPromises();

        expect(w.get('[data-thread-error]').isVisible()).toBe(false);
        expect(w.get('[data-thread-wrap]').isVisible()).toBe(true);
    });

    it('訊息載入失敗顯示 thread-error 且不顯示 emptyMessages', async () => {
        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 3, other_user: { id: 6, name: 'Dave' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        http.get.mockRejectedValueOnce(new Error('fail'));

        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        const btn = w.find('[data-convo-id]');
        await btn.trigger('click');
        await flushPromises();

        expect(w.get('[data-thread-error]').isVisible()).toBe(true);
        expect(w.get('[data-thread-wrap]').isVisible()).toBe(false);
    });
});

// ─────────────────────────────────────────────────────────────────
describe('ChatApp 送訊息', () => {
    it('樂觀送出顯示訊息且成功標記已送出', async () => {
        http.get.mockResolvedValue({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        await w.get('[data-convo-id="1"]').trigger('click');
        await flushPromises();

        http.post.mockResolvedValueOnce({ data: { message: { id: 100 } } });
        await w.get('#message-input').setValue('hello');
        await w.get('#message-form').trigger('submit');
        await flushPromises();

        expect(w.text()).toContain('hello');
        expect(http.post).toHaveBeenCalledWith('/chats/1/messages', { body: 'hello' });
        expect(w.text()).toContain('已送出');
    });

    it('送出失敗顯示重試', async () => {
        http.get.mockResolvedValue({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        await w.get('[data-convo-id="1"]').trigger('click');
        await flushPromises();

        http.post.mockRejectedValueOnce(new Error('net'));
        await w.get('#message-input').setValue('boom');
        await w.get('#message-form').trigger('submit');
        await flushPromises();

        expect(w.text()).toContain('傳送失敗');
    });

    // ── Dedup/claim test (4.4 reviewer request) ─────────────────────
    it('自身廣播到達後認領樂觀泡泡：不重複、狀態變已送出', async () => {
        http.get.mockResolvedValue({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        http.patch.mockResolvedValue({});
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        await w.get('[data-convo-id="1"]').trigger('click');
        await flushPromises();

        // POST resolves with id=42
        http.post.mockResolvedValueOnce({ data: { message: { id: 42 } } });
        await w.get('#message-input').setValue('test-dedup');
        await w.get('#message-form').trigger('submit');

        // Simulate broadcast arriving with the same body from meId=1 BEFORE post resolves
        // This calls appendMessage which should claim the pending optimistic bubble
        w.vm.appendMessage(
            { id: 42, sender_id: 1, body: 'test-dedup', conversation_id: 1, created_at: new Date().toISOString() },
            { live: true },
        );

        await flushPromises();

        // Count matching bubbles — must be exactly 1
        const bubbles = w.findAll('.chat-bubble');
        const matchingBubbles = bubbles.filter((b) => b.text() === 'test-dedup');
        expect(matchingBubbles).toHaveLength(1);
        expect(w.text()).toContain('已送出');
    });
});

// ── Presence / online dots ────────────────────────────────────────
describe('ChatApp 線上狀態 (Echo presence)', () => {
    it('echo.here 帶入線上使用者後對應對話顯示線上點', async () => {
        const echo = makeEchoMock();
        useEcho.mockReturnValue({ echo, userId: 1 });

        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });

        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        // Simulate presence 'here' with user id=9 online
        echo._presenceCallbacks.here([{ id: 9, name: 'Bob' }]);
        await flushPromises();

        // Verify via reactive state (isVisible() uses getComputedStyle which jsdom doesn't compute)
        expect(w.vm.onlineUsers.has(9)).toBe(true);
        // Also verify the DOM: v-show should have cleared display:none on the online dot
        const convoBtn = w.find('[data-other-id="9"]');
        expect(convoBtn.exists()).toBe(true);
        const onlineDot = convoBtn.find('.chat-online-dot');
        expect(onlineDot.exists()).toBe(true);
        expect(onlineDot.element.style.display).not.toBe('none');
    });

    it('echo.joining / leaving 動態更新線上點', async () => {
        const echo = makeEchoMock();
        useEcho.mockReturnValue({ echo, userId: 1 });

        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });

        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        // No one online initially
        echo._presenceCallbacks.here([]);
        await flushPromises();
        // Verify via reactive state (isVisible() uses getComputedStyle which jsdom doesn't compute)
        expect(w.vm.onlineUsers.has(9)).toBe(false);

        // User joins
        echo._presenceCallbacks.joining({ id: 9, name: 'Bob' });
        await flushPromises();
        expect(w.vm.onlineUsers.has(9)).toBe(true);
        // Also verify the DOM: v-show should have cleared display:none
        const dot = w.find('[data-other-id="9"]').find('.chat-online-dot');
        expect(dot.element.style.display).not.toBe('none');

        // User leaves
        echo._presenceCallbacks.leaving({ id: 9, name: 'Bob' });
        await flushPromises();
        expect(w.vm.onlineUsers.has(9)).toBe(false);
        expect(dot.element.style.display).toBe('none');
    });
});

// ── Typing whisper ────────────────────────────────────────────────
describe('ChatApp typing whisper (Echo)', () => {
    it('input 觸發 whisper("typing", ...) via echo.private', async () => {
        const echo = makeEchoMock();
        const w = await mountWithConversation({ echo });

        // Type into the input
        await w.get('#message-input').setValue('h');
        await w.get('#message-input').trigger('input');
        await flushPromises();

        const typingWhispers = echo._whisperCalls.filter((c) => c.event === 'typing');
        expect(typingWhispers.length).toBeGreaterThan(0);
        expect(typingWhispers[0].data).toMatchObject({ from: 1 });
    });

    it('echo 為 null 時 input 不拋出錯誤', async () => {
        // Default mock: echo = null
        const w = await mountWithConversation({ echo: null });

        await expect(async () => {
            await w.get('#message-input').setValue('x');
            await w.get('#message-input').trigger('input');
            await flushPromises();
        }).not.toThrow();
    });
});

// ── window chat:message listener ─────────────────────────────────
describe('ChatApp window chat:message 事件', () => {
    it('activeId 相符時即時 appendMessage 並 patch read', async () => {
        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        http.patch.mockResolvedValue({});
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        await w.get('[data-convo-id="1"]').trigger('click');
        await flushPromises();

        http.patch.mockResolvedValue({});
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        window.dispatchEvent(new CustomEvent('chat:message', {
            detail: {
                id: 77,
                conversation_id: 1,
                sender_id: 9,
                body: 'incoming msg',
                created_at: new Date().toISOString(),
            },
        }));
        await flushPromises();

        expect(w.text()).toContain('incoming msg');
        expect(http.patch).toHaveBeenCalledWith('/chats/1/read');
    });

    it('activeId 不符時僅 scheduleListReload 不 append', async () => {
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        window.dispatchEvent(new CustomEvent('chat:message', {
            detail: {
                id: 88,
                conversation_id: 999,
                sender_id: 9,
                body: 'other convo',
                created_at: new Date().toISOString(),
            },
        }));
        await flushPromises();

        expect(w.text()).not.toContain('other convo');
        expect(http.patch).not.toHaveBeenCalledWith('/chats/999/read');
    });
});

// ── userSelect / start new conversation ──────────────────────────
describe('ChatApp 開新對話 (userSelect)', () => {
    it('選擇使用者後 POST /chats/start 並開啟對話', async () => {
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });
        const w = mount(ChatApp, {
            props: { meId: 1, selectableUsers: [{ id: 5, name: 'Eve' }] },
        });
        await flushPromises();

        http.post.mockResolvedValueOnce({ data: { conversation_id: 10 } });
        http.get.mockResolvedValueOnce({
            data: {
                conversations: [
                    { id: 10, other_user: { id: 5, name: 'Eve' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        http.patch.mockResolvedValue({});
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        const select = w.find('select');
        await select.setValue('5');
        await select.trigger('change');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/chats/start', { target_user_id: 5 });
        expect(w.vm.activeId.valueOf()).toBe(10);
    });
});

// ── Echo null graceful degradation ───────────────────────────────
describe('ChatApp 無 Echo 時不 crash', () => {
    it('echo = null 時掛載、開對話、送訊息均正常', async () => {
        // echo is null (default)
        http.get.mockResolvedValue({
            data: {
                conversations: [
                    { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 },
                ],
            },
        });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        http.patch.mockResolvedValue({});
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        await w.get('[data-convo-id="1"]').trigger('click');
        await flushPromises();

        http.post.mockResolvedValueOnce({ data: { message: { id: 1 } } });
        await w.get('#message-input').setValue('no echo msg');
        await w.get('#message-form').trigger('submit');
        await flushPromises();

        expect(w.text()).toContain('no echo msg');
    });
});
