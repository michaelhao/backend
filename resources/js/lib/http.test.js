import { describe, it, expect } from 'vitest';
import http from '@/lib/http';

describe('http', () => {
    it('預設帶 X-Requested-With header', () => {
        expect(http.defaults.headers.common['X-Requested-With']).toBe('XMLHttpRequest');
    });
    it('是一個帶 get/post 的 axios 實例', () => {
        expect(typeof http.get).toBe('function');
        expect(typeof http.post).toBe('function');
    });
});
