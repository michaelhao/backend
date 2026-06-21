import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
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
afterEach(() => { document.body.innerHTML = ''; });

describe('GradeWeightField', () => {
    it('weight input 有 name="weight" 屬性', () => {
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight' } });
        expect(w.get('#weight').attributes('name')).toBe('weight');
    });

    it('currentWeight prop 初始化 weight 輸入值', () => {
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight', currentWeight: 15 } });
        expect(w.get('#weight').element.value).toBe('15');
    });

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
        // 設置外部 Blade #name input,讓 onMounted 能同步名稱
        const container = document.createElement('div');
        document.body.innerHTML = '<input id="name" value="進階版">';
        document.body.appendChild(container);
        const w = mount(GradeWeightField, {
            props: { excludeId: null, grades, checkUrl: '/grades/check-weight' },
            attachTo: container,
        });
        await w.get('#weight').setValue('25');
        await w.get('#weight').trigger('change');
        await flushPromises();
        const preview = w.get('.weight-preview');
        expect(preview.text()).toContain('進階版');
        expect(preview.text()).toContain('25');
    });
});
