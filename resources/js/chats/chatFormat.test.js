import { describe, it, expect } from 'vitest';
import { initials, isSameDay, dayKey, formatTime, formatDayLabel, formatListTime, escapeHtml, yesterdayOf } from '@/chats/chatFormat';

describe('chatFormat', () => {
    // initials tests
    it('initials 單名取首字', () => {
        expect(initials('Amy')).toBe('A');
    });

    it('initials 雙名取兩首字大寫', () => {
        expect(initials('Amy Lee')).toBe('AL');
    });

    it('initials 空字串回 ?', () => {
        expect(initials('')).toBe('?');
    });

    // isSameDay tests
    it('isSameDay 同日為真', () => {
        expect(isSameDay(new Date('2026-06-18T01:00'), new Date('2026-06-18T23:00'))).toBe(true);
    });

    it('isSameDay 不同日為假', () => {
        expect(isSameDay(new Date('2026-06-18T01:00'), new Date('2026-06-19T01:00'))).toBe(false);
    });

    // dayKey tests
    it('dayKey 返回正確的日期鍵', () => {
        const d = new Date('2026-06-18T12:00:00');
        expect(dayKey(d)).toBe(`${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`);
    });

    // escapeHtml tests
    it('escapeHtml 轉義 HTML 特殊字元', () => {
        expect(escapeHtml('<')).toContain('&lt;');
    });

    it('escapeHtml 回傳純文字', () => {
        expect(escapeHtml('hello')).toBe('hello');
    });
});
