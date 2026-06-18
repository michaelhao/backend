import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import ShopEditPanel from '@/shops/ShopEditPanel.vue';

vi.mock('@/lib/http', () => ({ default: { post: vi.fn() } }));

function setupDOM() {
    document.body.innerHTML = `
        <span id="admin-email-masked"
              class="flex-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 font-mono">
            t**t@e*****e.com
        </span>
        <input id="admin-email-input" type="email" name="admin[email]"
               value="test@example.com"
               class="hidden flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm">
        <button type="button" id="admin-email-toggle">修改</button>

        <button type="button" id="open-cert-modal">進行認證</button>

        <input type="text" id="business-number-display" value="" readonly>
        <input type="hidden" id="business-number-hidden" name="admin[business_number]" value="">
        <input type="text" id="company-name-display" value="" readonly>
        <input type="hidden" id="company-name-hidden" name="admin[company_name]" value="">

        <div id="shop-edit-panel"></div>
    `;
}

describe('ShopEditPanel', () => {
    beforeEach(() => {
        setupDOM();
        http.post.mockReset();
    });

    it('點 #admin-email-toggle 切換至可編輯狀態', async () => {
        mount(ShopEditPanel, {
            attachTo: '#shop-edit-panel',
            props: { certRoute: '/shops/1/certify', adminEmailError: false },
        });

        const toggle = document.getElementById('admin-email-toggle');
        const masked = document.getElementById('admin-email-masked');
        const input = document.getElementById('admin-email-input');

        expect(masked.classList.contains('hidden')).toBe(false);
        expect(input.classList.contains('hidden')).toBe(true);

        await toggle.click();
        await flushPromises();

        expect(masked.classList.contains('hidden')).toBe(true);
        expect(input.classList.contains('hidden')).toBe(false);
        expect(toggle.textContent).toBe('取消');
    });

    it('再次點 toggle 切回遮罩狀態', async () => {
        mount(ShopEditPanel, {
            attachTo: '#shop-edit-panel',
            props: { certRoute: '/shops/1/certify', adminEmailError: false },
        });

        const toggle = document.getElementById('admin-email-toggle');
        const masked = document.getElementById('admin-email-masked');
        const input = document.getElementById('admin-email-input');

        await toggle.click();
        await flushPromises();
        await toggle.click();
        await flushPromises();

        expect(masked.classList.contains('hidden')).toBe(false);
        expect(input.classList.contains('hidden')).toBe(true);
        expect(toggle.textContent).toBe('修改');
    });

    it('adminEmailError 為 true 時初始展開為可編輯狀態', async () => {
        mount(ShopEditPanel, {
            attachTo: '#shop-edit-panel',
            props: { certRoute: '/shops/1/certify', adminEmailError: true },
        });

        await flushPromises();

        const masked = document.getElementById('admin-email-masked');
        const input = document.getElementById('admin-email-input');
        const toggle = document.getElementById('admin-email-toggle');

        expect(masked.classList.contains('hidden')).toBe(true);
        expect(input.classList.contains('hidden')).toBe(false);
        expect(toggle.textContent).toBe('取消');
    });

    it('非 8 碼統編顯示驗證錯誤且不呼叫 http', async () => {
        const wrapper = mount(ShopEditPanel, {
            attachTo: '#shop-edit-panel',
            props: { certRoute: '/shops/1/certify', adminEmailError: false },
        });

        // Open modal
        await document.getElementById('open-cert-modal').click();
        await flushPromises();

        expect(wrapper.find('.modal-panel').exists()).toBe(true);

        // Enter invalid input
        const certInput = wrapper.find('#cert-business-number');
        await certInput.setValue('1234');

        // Click submit
        await wrapper.find('button[type="button"]:not([class*="gray"])').trigger('click');
        await flushPromises();

        expect(wrapper.find('p').text()).toBe('請輸入 8 位數字');
        expect(http.post).not.toHaveBeenCalled();
    });

    it('有效 8 碼 + http.post 成功 → 填入 hidden/display 欄位', async () => {
        http.post.mockResolvedValue({
            data: { success: true, company_name: '某公司' },
        });

        const wrapper = mount(ShopEditPanel, {
            attachTo: '#shop-edit-panel',
            props: { certRoute: '/shops/1/certify', adminEmailError: false },
        });

        // Open modal
        await document.getElementById('open-cert-modal').click();
        await flushPromises();

        // Enter valid input
        const certInput = wrapper.find('#cert-business-number');
        await certInput.setValue('12345678');

        // Click submit (the 認證 button — not the cancel button)
        const submitBtn = wrapper.findAll('button[type="button"]').find(
            (b) => b.text() === '認證',
        );
        await submitBtn.trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/shops/1/certify', { business_number: '12345678' });

        // Hidden/display inputs filled
        expect(document.getElementById('business-number-hidden').value).toBe('12345678');
        expect(document.getElementById('business-number-display').value).toBe('1*3*5*7*');
        expect(document.getElementById('company-name-hidden').value).toBe('某公司');
        expect(document.getElementById('company-name-display').value).toBe('某公司');

        // Success message shown
        expect(wrapper.text()).toContain('認證成功');
        expect(wrapper.text()).toContain('某公司');

        // Button changes to 完成
        expect(wrapper.text()).toContain('完成');
    });

    it('http.post 失敗顯示認證失敗訊息', async () => {
        http.post.mockRejectedValue(new Error('network error'));

        const wrapper = mount(ShopEditPanel, {
            attachTo: '#shop-edit-panel',
            props: { certRoute: '/shops/1/certify', adminEmailError: false },
        });

        await document.getElementById('open-cert-modal').click();
        await flushPromises();

        const certInput = wrapper.find('#cert-business-number');
        await certInput.setValue('12345678');

        const submitBtn = wrapper.findAll('button[type="button"]').find(
            (b) => b.text() === '認證',
        );
        await submitBtn.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('認證失敗，請確認統一編號是否正確');
    });

    it('點取消按鈕關閉 modal', async () => {
        const wrapper = mount(ShopEditPanel, {
            attachTo: '#shop-edit-panel',
            props: { certRoute: '/shops/1/certify', adminEmailError: false },
        });

        await document.getElementById('open-cert-modal').click();
        await flushPromises();
        expect(wrapper.find('.modal-panel').exists()).toBe(true);

        const cancelBtn = wrapper.findAll('button[type="button"]').find(
            (b) => b.text() === '取消',
        );
        await cancelBtn.trigger('click');
        await flushPromises();

        expect(wrapper.find('.modal-panel').exists()).toBe(false);
    });
});
