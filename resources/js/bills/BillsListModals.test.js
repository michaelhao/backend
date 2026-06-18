import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import BillsListModals from '@/bills/BillsListModals.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }));

beforeEach(() => {
    vi.clearAllMocks();
    document.body.innerHTML = `
        <div class="flash-area"></div>
        <button class="detail-btn" data-bill-id="7" data-bill-no="B-007">明細</button>
        <button class="writeoff-btn" data-bill-id="7" data-bill-no="B-007">銷帳</button>
        <button class="edit-btn" data-bill-id="7" data-bill-no="B-007" data-payment-status="2" data-paid-at="" data-invoice-no="">編輯帳務</button>
        <div id="bills-modals"></div>`;
});

describe('BillsListModals 明細', () => {
    it('開明細載入並渲染明細與總額', async () => {
        http.get.mockResolvedValue({ data: {
            bill: { shop_name: 'A店', creator_name: 'Amy', status_label: '已付款', status_class: 'x', payment_status: 1, total_grade: 1000, total_addons: 200, discount_amount: 100, total: 1100 },
            details: [{ name: '旗艦版', type: 1, total_price: 1000, start_at: '2026-01-01', expired_at: '2026-12-31', is_effective: 1 }],
        } });
        const w = mount(BillsListModals, { attachTo: '#bills-modals' });
        await document.querySelector('.detail-btn').click();
        await flushPromises();
        expect(w.text()).toContain('旗艦版');
        expect(w.text()).toContain('1,100');
        expect(http.get).toHaveBeenCalledWith('/bills/7/detail');
    });
});

describe('BillsListModals 銷帳', () => {
    it('未勾選不送出', async () => {
        http.get.mockResolvedValue({ data: { details: [{ id: 1, name: 'x', type: 1, total_price: 1, is_effective: 1 }] } });
        const w = mount(BillsListModals, { attachTo: '#bills-modals' });
        await document.querySelector('.writeoff-btn').click();
        await flushPromises();
        await w.get('[data-writeoff-confirm]').trigger('click');
        expect(http.post).not.toHaveBeenCalled();
    });
});

describe('BillsListModals 編輯', () => {
    it('送出 PATCH 成功後 reload', async () => {
        const reload = vi.fn();
        Object.defineProperty(window, 'location', { value: { reload }, writable: true });
        http.patch.mockResolvedValue({ data: {} });

        const w = mount(BillsListModals, { attachTo: '#bills-modals' });
        await document.querySelector('.edit-btn').click();
        await flushPromises();

        await w.get('[data-edit-confirm]').trigger('click');
        await flushPromises();

        expect(http.patch).toHaveBeenCalledWith('/bills/7', {
            payment_status: 2,
            paid_at: null,
            invoice_no: null,
        });
        expect(reload).toHaveBeenCalled();
    });
});
