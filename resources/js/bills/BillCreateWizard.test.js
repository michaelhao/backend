import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import BillCreateWizard from '@/bills/BillCreateWizard.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const props = {
    shopSearchUrl: '/bills/shop-search',
    shopInfoUrl: '/bills/shop-info',
    calculateUrl: '/bills/calculate',
    today: '2026-06-18',
};

beforeEach(() => {
    vi.clearAllMocks();
    document.body.innerHTML = '<div class="flash-area"></div>';
});

// ─── Shop search ──────────────────────────────────────────────

describe('BillCreateWizard 搜尋', () => {
    it('搜尋呼叫 API 並渲染候選', async () => {
        http.get.mockResolvedValue({ data: { shops: [{ id: 1, label: 'A 店' }] } });
        const w = mount(BillCreateWizard, { props });
        await w.get('#shop-keyword').setValue('A');
        await w.get('#shop-search-btn').trigger('click');
        await flushPromises();
        expect(http.get).toHaveBeenCalledWith('/bills/shop-search', { params: { keyword: 'A' } });
        expect(w.text()).toContain('A 店');
    });

    it('搜尋空字串不呼叫 API', async () => {
        const w = mount(BillCreateWizard, { props });
        await w.get('#shop-search-btn').trigger('click');
        await flushPromises();
        expect(http.get).not.toHaveBeenCalled();
    });

    it('搜尋無結果顯示提示', async () => {
        http.get.mockResolvedValue({ data: { shops: [] } });
        const w = mount(BillCreateWizard, { props });
        await w.get('#shop-keyword').setValue('xyz');
        await w.get('#shop-search-btn').trigger('click');
        await flushPromises();
        expect(w.text()).toContain('找不到符合的商店');
    });
});

// ─── Shop confirm + step advance ─────────────────────────────

const shopInfoData = {
    shop: { id: 10, name: '測試店', grade: '旗艦版', grade_id: 2, grade_weight: 10, grade_price: 500, status: '正常', expired_at: '2026-12-31' },
    grades: [
        { id: 1, name: '基本版', weight: 5, price: 200, addons: [] },
        { id: 2, name: '旗艦版', weight: 10, price: 500, addons: [] },
        { id: 3, name: '進階版', weight: 15, price: 900, addons: [] },
    ],
    addons: [{ id: 1, name: '額外空間', price: 100, type: 1 }, { id: 2, name: '配額項目', price: 200, type: 2 }],
    shop_addons: [],
    pending_bill_count: 0,
};

async function mountAndSelectShop(w, pendingCount = 0) {
    const data = { ...shopInfoData, pending_bill_count: pendingCount };
    // Use mockResolvedValue (not Once) for search so that the debounce re-call also
    // returns shops — then override with shopInfo mock right before confirm.
    http.get.mockResolvedValue({ data: { shops: [{ id: 10, label: '測試店' }] } });

    await w.get('#shop-keyword').setValue('測試');
    await w.get('#shop-search-btn').trigger('click');
    // Wait beyond debounce so it fires and consumes the search mock
    await new Promise((r) => setTimeout(r, 350));
    await flushPromises();

    // click the shop option
    await w.get('.shop-option').trigger('click');

    // Switch mock to shopInfo before confirm
    http.get.mockResolvedValueOnce({ data });

    await w.get('#shop-confirm-btn').trigger('click');
    await flushPromises();
}

describe('BillCreateWizard 確認商店', () => {
    it('成功載入進入 step3 並顯示商店資訊', async () => {
        const w = mount(BillCreateWizard, { props });
        await mountAndSelectShop(w);
        expect(w.find('#step-3').isVisible()).toBe(true);
        expect(w.find('#step-1').isVisible()).toBe(false);
        expect(w.text()).toContain('測試店');
        expect(w.text()).toContain('旗艦版');
    });

    it('pending_bill_count > 0 顯示警告', async () => {
        const w = mount(BillCreateWizard, { props });
        await mountAndSelectShop(w, 2);
        const warn = w.find('#pending-bill-warning');
        expect(warn.isVisible()).toBe(true);
        expect(warn.text()).toContain('2 張待處理帳單');
    });

    it('pending_bill_count = 0 隱藏警告', async () => {
        const w = mount(BillCreateWizard, { props });
        await mountAndSelectShop(w, 0);
        expect(w.find('#pending-bill-warning').isVisible()).toBe(false);
    });

    it('載入失敗回到 step1 並 flash 錯誤', async () => {
        // Use persistent mock for search so debounce re-call doesn't break sequence
        http.get.mockResolvedValue({ data: { shops: [{ id: 10, label: '測試店' }] } });

        const w = mount(BillCreateWizard, { props });
        await w.get('#shop-keyword').setValue('測試');
        await w.get('#shop-search-btn').trigger('click');
        // Wait beyond debounce to let it fire
        await new Promise((r) => setTimeout(r, 350));
        await flushPromises();
        await w.get('.shop-option').trigger('click');

        // Now override: next call (shopInfo) will reject
        http.get.mockRejectedValueOnce({ response: { data: { message: '商店不存在' } } });

        await w.get('#shop-confirm-btn').trigger('click');
        await flushPromises();

        expect(w.find('#step-1').isVisible()).toBe(true);
        expect(w.find('#step-3').isVisible()).toBe(false);
        // Flash area should have an error message
        expect(document.querySelector('.flash-area')?.innerHTML).toContain('商店不存在');
    });
});

// ─── Discount validation ─────────────────────────────────────

describe('BillCreateWizard 折抵', () => {
    // Helper: mount, go through search/confirm/grade to get a non-zero subtotal.
    // Uses the same mountAndSelectShop pattern but with grade selection afterward.
    async function mountWithSubtotal() {
        // search always returns candidates; shopInfo returns full data; calculate returns price
        http.get
            .mockResolvedValue({ data: { shops: [{ id: 10, label: '測試店' }] } });

        const w = mount(BillCreateWizard, { props, attachTo: document.body });

        // Step 1: search and select shop
        await w.get('#shop-keyword').setValue('測試');
        await w.get('#shop-search-btn').trigger('click');
        await flushPromises();
        // debounce fires 300ms later — wait for it so it consumes search mock too
        await new Promise((r) => setTimeout(r, 350));
        await flushPromises();
        await w.get('.shop-option').trigger('click');

        // Now switch mock for shopInfo
        http.get.mockResolvedValueOnce({ data: { ...shopInfoData } });
        // And set default for calculate
        http.get.mockResolvedValue({ data: { total_price: 1000, expired_at: '2027-06-30' } });

        await w.get('#shop-confirm-btn').trigger('click');
        await flushPromises();

        // Enable grade
        await w.get('#toggle-grade-btn').trigger('click');
        await flushPromises();

        // Select grade (進階版 id=3, weight=15 > currentWeight=10)
        await w.get('#grade-select').setValue('3');
        await w.get('#grade-select').trigger('change');
        await flushPromises();

        // Select months
        await w.get('#grade-months').setValue('12');
        await w.get('#grade-months').trigger('change');
        await flushPromises();

        return w;
    }

    it('折抵超過小計顯示錯誤', async () => {
        const w = await mountWithSubtotal();

        // Subtotal should be > 0 after grade calculate → discount block visible
        expect(w.find('#discount-block').isVisible()).toBe(true);

        // Simulate user setting a discount amount larger than subtotal
        // The input is disabled by default (no typeId selected). We bypass by
        // directly updating the component's discount reactive state via the exposed
        // input event with a large value.
        const discAmt = w.find('#discount-amount');
        // Remove disabled to allow setValue (mirrors real usage: user picks a type first)
        discAmt.element.disabled = false;
        await discAmt.setValue(99999);
        await discAmt.trigger('input');

        expect(w.find('#discount-error').isVisible()).toBe(true);
        expect(w.find('#discount-error').text()).toContain('不得大於小計');
    });

    it('折抵超過小計時送出被阻擋(error 仍顯示)', async () => {
        const w = await mountWithSubtotal();

        const discAmt = w.find('#discount-amount');
        discAmt.element.disabled = false;
        await discAmt.setValue(99999);
        await discAmt.trigger('input');

        // Click the submit button — component validates and shows error
        await w.get('button[type="submit"]').trigger('click');
        await flushPromises();

        expect(w.find('#discount-error').isVisible()).toBe(true);
    });
});

// ─── Quota addon (CRITICAL 1) ─────────────────────────────────

describe('BillCreateWizard 配額型 addon', () => {
    async function mountWithAddon() {
        http.get.mockResolvedValue({ data: { shops: [{ id: 10, label: '測試店' }] } });
        const w = mount(BillCreateWizard, { props, attachTo: document.body });

        // Step 1: search and select
        await w.get('#shop-keyword').setValue('測試');
        await w.get('#shop-search-btn').trigger('click');
        await new Promise((r) => setTimeout(r, 350));
        await flushPromises();
        await w.get('.shop-option').trigger('click');

        // shopInfo returns data with quota addon (type: 2, integer)
        http.get.mockResolvedValueOnce({ data: { ...shopInfoData } });
        // calculate default
        http.get.mockResolvedValue({ data: { total_price: 500, expired_at: '2027-06-30' } });

        await w.get('#shop-confirm-btn').trigger('click');
        await flushPromises();

        // Enable addon
        await w.get('#toggle-addon-btn').trigger('click');
        await flushPromises();

        return w;
    }

    it('配額型 addon (type:2 整數) 選中後數量欄位顯示', async () => {
        const w = await mountWithAddon();

        // Select the quota addon (id=2, type:2)
        const addonSelect = w.find('.addon-select');
        await addonSelect.setValue('2');
        await addonSelect.trigger('change');
        await flushPromises();

        // The qty column should now be visible
        const qtyCol = w.find('.addon-qty-col');
        expect(qtyCol.classes()).not.toContain('hidden');
    });

    it('配額型 addon qty=3 時計算金額為 unit total × 3', async () => {
        const w = await mountWithAddon();

        // calculate returns total_price=500 per unit
        http.get.mockResolvedValue({ data: { total_price: 500, expired_at: '2027-06-30' } });

        // Select quota addon
        const addonSelect = w.find('.addon-select');
        await addonSelect.setValue('2');
        await addonSelect.trigger('change');
        await flushPromises();

        // Set months
        const monthsSel = w.find('.addon-months');
        await monthsSel.setValue('1');
        await monthsSel.trigger('change');
        await flushPromises();

        // Set qty to 3
        const qtyInput = w.find('.addon-qty');
        await qtyInput.setValue(3);
        await qtyInput.trigger('input');
        await flushPromises();

        // Amount displayed should be 500 * 3 = 1500
        const amountInput = w.find('.addon-amount');
        expect(amountInput.element.value).toContain('1,500');
    });
});
