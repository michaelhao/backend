import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SessionTimer from './SessionTimer.vue';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

const factory = (props = {}) => mount(SessionTimer, {
    props: { lifetime: 3600, loginUrl: '/login', ...props },
});

describe('SessionTimer', () => {
    it('(a) 以 HH:MM:SS 格式顯示初始時間', () => {
        const w = factory({ lifetime: 3661 });
        expect(w.text()).toBe('01:01:01');
    });

    it('(b) 每秒遞減一秒', async () => {
        const w = factory({ lifetime: 3661 });
        expect(w.text()).toBe('01:01:01');
        await vi.advanceTimersByTimeAsync(1000);
        expect(w.text()).toBe('01:01:00');
    });

    it('(c) remaining > 300 時套用 text-slate-400,≤ 300 後換 text-red-500', async () => {
        const w = factory({ lifetime: 301 });
        // 初始 remaining=301，不應為紅色
        expect(w.find('span').classes()).toContain('text-slate-400');
        expect(w.find('span').classes()).not.toContain('text-red-500');
        // 前進 1 秒 → remaining=300，應變紅
        await vi.advanceTimersByTimeAsync(1000);
        expect(w.find('span').classes()).toContain('text-red-500');
        expect(w.find('span').classes()).not.toContain('text-slate-400');
    });
});
