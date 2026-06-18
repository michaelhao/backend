import { describe, it, expect, vi, beforeEach } from 'vitest';
import http from '@/lib/http';
import { useChatBadge } from '@/composables/useChatBadge';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn() } }));
beforeEach(() => {
    http.get.mockReset();
    document.body.innerHTML = '<span id="chat-unread-badge" class="hidden"></span>';
});

describe('useChatBadge', () => {
    it('未讀 > 0 顯示數字', async () => {
        http.get.mockResolvedValue({ data: { unread_count: 3 } });
        await useChatBadge().refresh();
        const b = document.getElementById('chat-unread-badge');
        expect(b.textContent).toBe('3');
        expect(b.classList.contains('hidden')).toBe(false);
    });
    it('未讀 0 隱藏', async () => {
        http.get.mockResolvedValue({ data: { unread_count: 0 } });
        await useChatBadge().refresh();
        expect(document.getElementById('chat-unread-badge').classList.contains('hidden')).toBe(true);
    });
});
