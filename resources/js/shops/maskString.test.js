import { describe, it, expect } from 'vitest';
import { maskString } from '@/shops/maskString';

describe('maskString', () => {
    it('奇數索引換星號', () => {
        expect(maskString('12345678')).toBe('1*3*5*7*');
    });

    it('空字串返回空字串', () => {
        expect(maskString('')).toBe('');
    });

    it('單字元不遮', () => {
        expect(maskString('A')).toBe('A');
    });

    it('兩字元只遮第二個', () => {
        expect(maskString('AB')).toBe('A*');
    });
});
