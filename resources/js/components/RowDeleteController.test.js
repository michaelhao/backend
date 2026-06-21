import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import RowDeleteController from '@/components/RowDeleteController.vue';

vi.mock('@/lib/http', () => ({ default: { delete: vi.fn() } }));

beforeEach(() => {
    http.delete.mockReset();
    document.body.innerHTML = `
        <div class="flash-area"></div>
        <table><tbody>
          <tr><td><button class="delete-btn" data-url="/users/5" data-name="Amy">刪除</button></td></tr>
        </tbody></table>
        <div id="row-delete"></div>`;
});

describe('RowDeleteController', () => {
    it('點 delete-btn 開 modal 顯示名稱', async () => {
        const w = mount(RowDeleteController, { attachTo: '#row-delete' });
        await document.querySelector('.delete-btn').click();
        await flushPromises();
        expect(w.text()).toContain('Amy');
    });

    it('確認後 DELETE 成功移除該列', async () => {
        http.delete.mockResolvedValue({ data: { message: '已刪除' } });
        const w = mount(RowDeleteController, { attachTo: '#row-delete' });
        await document.querySelector('.delete-btn').click();
        await flushPromises();
        await w.get('[data-confirm]').trigger('click');
        await flushPromises();
        expect(http.delete).toHaveBeenCalledWith('/users/5');
        expect(document.querySelector('tr')).toBeNull();
        expect(document.querySelector('.flash-area').textContent).toContain('已刪除');
    });
});
