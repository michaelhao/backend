import { describe, it, expect } from 'vitest';
import { monthsOptions, paymentTypeFromMonths, fmt } from '@/bills/billMath';

describe('billMath', () => {
    it('fmt 加千分位與 NT$', () => {
        expect(fmt(1234567)).toBe('NT$1,234,567');
    });

    it('paymentTypeFromMonths 對照', () => {
        expect(paymentTypeFromMonths(1)).toBe(1);
        expect(paymentTypeFromMonths(3)).toBe(2);
        expect(paymentTypeFromMonths(12)).toBe(3);
        expect(paymentTypeFromMonths(24)).toBe(3);
        expect(paymentTypeFromMonths(36)).toBe(3);
        expect(paymentTypeFromMonths(5)).toBeNull();
    });

    it('monthsOptions 月初不含「月底」選項', () => {
        const opts = monthsOptions('2026-03-01');
        expect(opts.find((o) => o.v === 0)).toBeUndefined();
        expect(opts).toHaveLength(36);
    });

    it('monthsOptions 月中含「月底」', () => {
        const opts = monthsOptions('2026-03-15');
        expect(opts[0]).toEqual({ v: 0, l: '月底' });
        expect(opts).toHaveLength(37);
    });

    it('monthsOptions 月末不含「月底」', () => {
        const opts = monthsOptions('2026-03-31');
        expect(opts.find((o) => o.v === 0)).toBeUndefined();
        expect(opts).toHaveLength(36);
    });

    it('monthsOptions 特殊月數標籤', () => {
        const opts = monthsOptions('2026-03-01');
        const labels = Object.fromEntries(opts.map((o) => [o.v, o.l]));
        expect(labels[6]).toBe('6 個月（半年）');
        expect(labels[12]).toBe('12 個月（年繳）');
        expect(labels[24]).toBe('24 個月（2 年繳）');
        expect(labels[36]).toBe('36 個月（3 年繳）');
    });
});
