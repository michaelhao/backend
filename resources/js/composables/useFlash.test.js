import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useFlash } from '@/composables/useFlash';

beforeEach(() => {
    document.body.innerHTML = '<div class="flash-area"></div>';
    vi.useFakeTimers();
});

describe('useFlash', () => {
    it('showFlash 插入訊息到 flash-area', () => {
        useFlash().showFlash('success', '已儲存');
        const el = document.querySelector('.flash-area .flash-message');
        expect(el).not.toBeNull();
        expect(el.textContent).toBe('已儲存');
        expect(el.className).toContain('flash-success');
    });
    it('5 秒後淡出並移除', () => {
        useFlash().showFlash('error', '失敗');
        vi.advanceTimersByTime(5000 + 600);
        expect(document.querySelector('.flash-message')).toBeNull();
    });
});
