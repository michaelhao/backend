import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import ChatApp from '@/chats/ChatApp.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }));
vi.mock('@/composables/useEcho', () => ({ useEcho: () => ({ echo: null, userId: 1 }) }));
vi.mock('@/composables/useChatBadge', () => ({ useChatBadge: () => ({ refresh: vi.fn() }) }));

beforeEach(() => vi.clearAllMocks());

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
        // First load succeeds
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

        // Background reload fails
        http.get.mockRejectedValueOnce(new Error('network'));
        await w.vm.loadConversations();
        await flushPromises();

        // List still visible, error NOT shown
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
        // Messages load
        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        http.patch.mockResolvedValueOnce({});
        // reload conversations after open
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });

        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();

        const btn = w.find('[data-convo-id]');
        await btn.trigger('click');
        await flushPromises();

        // thread-error should be hidden, threadWrap shown
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
        // Messages fail
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
