import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import GradeWeightField from '@/grades/GradeWeightField.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn() } }));

const grades = [
    { id: 1, name: '旗艦版', weight: 30 },
    { id: 2, name: '標準版', weight: 20 },
    { id: 3, name: '入門版', weight: 10 },
];

beforeEach(() => { http.get.mockReset(); });

describe('GradeWeightField', () => {
    it('權重小於 1 顯示錯誤並停用送出', async () => {
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight' } });
        await w.get('#weight').setValue('0');
        await w.get('#weight').trigger('change');
        await flushPromises();
        expect(w.get('#weight-error').text()).toContain('最低為 1');
        expect(http.get).not.toHaveBeenCalled();
    });

    it('重複權重顯示「請確認版本權重」並標紅衝突列', async () => {
        http.get.mockResolvedValue({ data: { duplicate: true, conflicting_grade: { id: 2 }, grades } });
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight' } });
        await w.get('#weight').setValue('20');
        await w.get('#weight').trigger('change');
        await flushPromises();
        expect(w.get('#weight-error').text()).toContain('請確認版本權重');
        expect(w.get('.weight-row[data-id="2"]').classes()).toContain('text-red-600');
    });

    it('合法權重插入預覽列(建立模式)', async () => {
        http.get.mockResolvedValue({ data: { duplicate: false, grades } });
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight' } });
        await w.get('#name').setValue('進階版');
        await w.get('#weight').setValue('25');
        await w.get('#weight').trigger('change');
        await flushPromises();
        const preview = w.get('.weight-preview');
        expect(preview.text()).toContain('進階版');
        expect(preview.text()).toContain('25');
    });
});
