import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import GradeToggleController from '@/grades/GradeToggleController.vue';

vi.mock('@/lib/http', () => ({ default: { patch: vi.fn() } }));

beforeEach(() => {
    http.patch.mockReset();
    document.body.innerHTML = `
        <div class="flash-area"></div>
        <button class="toggle-btn bg-green-500" data-active="1" data-name="旗艦版" data-url="/grades/1/toggle">
          <span class="translate-x-6"></span>
        </button>
        <div id="grade-toggle"></div>`;
});

describe('GradeToggleController', () => {
    it('停用後切換鈕樣式 + flash', async () => {
        http.patch.mockResolvedValue({ data: {} });
        const w = mount(GradeToggleController, { attachTo: '#grade-toggle' });
        await document.querySelector('.toggle-btn').click();
        await flushPromises();
        expect(w.text()).toContain('停用');
        await w.get('[data-confirm]').trigger('click');
        await flushPromises();
        const btn = document.querySelector('.toggle-btn');
        expect(btn.dataset.active).toBe('0');
        expect(btn.classList.contains('bg-gray-300')).toBe(true);
        expect(document.querySelector('.flash-area').textContent).toContain('版本狀態已更新');
    });

    it('操作失敗時按鈕狀態不變 + 錯誤 flash', async () => {
        http.patch.mockRejectedValue({ response: { data: { message: '操作失敗' } } });
        const w = mount(GradeToggleController, { attachTo: '#grade-toggle' });
        await document.querySelector('.toggle-btn').click();
        await flushPromises();
        expect(w.text()).toContain('停用');
        await w.get('[data-confirm]').trigger('click');
        await flushPromises();
        const btn = document.querySelector('.toggle-btn');
        expect(btn.dataset.active).toBe('1');
        expect(btn.classList.contains('bg-green-500')).toBe(true);
        expect(document.querySelector('.flash-area').textContent).toContain('操作失敗');
    });

    it('啟用後切換鈕樣式 + flash', async () => {
        http.patch.mockResolvedValue({ data: {} });
        document.body.innerHTML = `
            <div class="flash-area"></div>
            <button class="toggle-btn bg-gray-300" data-active="0" data-name="旗艦版" data-url="/grades/1/toggle">
              <span class="translate-x-1"></span>
            </button>
            <div id="grade-toggle"></div>`;
        const w = mount(GradeToggleController, { attachTo: '#grade-toggle' });
        await document.querySelector('.toggle-btn').click();
        await flushPromises();
        expect(w.text()).toContain('啟用');
        await w.get('[data-confirm]').trigger('click');
        await flushPromises();
        const btn = document.querySelector('.toggle-btn');
        expect(btn.dataset.active).toBe('1');
        expect(btn.classList.contains('bg-green-500')).toBe(true);
        expect(document.querySelector('.flash-area').textContent).toContain('版本狀態已更新');
    });
});
