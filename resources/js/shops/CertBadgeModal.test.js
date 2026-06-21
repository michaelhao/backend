import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import CertBadgeModal from './CertBadgeModal.vue';

function setupDOM() {
    document.body.innerHTML = `
        <button class="cert-badge"
            data-business-number="12345678"
            data-company-name="某公司">已認證</button>
        <div id="cert-badge-modal"></div>
    `;
}

describe('CertBadgeModal', () => {
    beforeEach(() => {
        setupDOM();
    });

    it('點擊 .cert-badge 後 modal 顯示統編與公司名', async () => {
        const wrapper = mount(CertBadgeModal, { attachTo: document.getElementById('cert-badge-modal') });

        const badge = document.querySelector('.cert-badge');
        await badge.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.modal-panel').exists()).toBe(true);
        expect(wrapper.text()).toContain('12345678');
        expect(wrapper.text()).toContain('某公司');
    });

    it('點擊關閉按鈕後 modal 隱藏', async () => {
        const wrapper = mount(CertBadgeModal, { attachTo: document.getElementById('cert-badge-modal') });

        const badge = document.querySelector('.cert-badge');
        await badge.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.modal-panel').exists()).toBe(true);

        await wrapper.find('[data-close]').trigger('click');

        expect(wrapper.find('.modal-panel').exists()).toBe(false);
    });
});
