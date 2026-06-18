import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ConfirmModal from '@/components/ConfirmModal.vue';

const factory = (props = {}) => mount(ConfirmModal, {
    props: { open: true, title: '刪除', name: '測試項目', actionLabel: '確認刪除', busy: false, ...props },
    attachTo: document.body,
});

describe('ConfirmModal', () => {
    it('open 為 false 時不顯示內容', () => {
        const w = factory({ open: false });
        expect(w.find('.modal-panel').exists()).toBe(false);
    });
    it('顯示 name 與 actionLabel', () => {
        const w = factory();
        expect(w.text()).toContain('測試項目');
        expect(w.text()).toContain('確認刪除');
    });
    it('點確認鈕 emit confirm', async () => {
        const w = factory();
        await w.get('[data-confirm]').trigger('click');
        expect(w.emitted('confirm')).toBeTruthy();
    });
    it('點取消鈕 emit cancel', async () => {
        const w = factory();
        await w.get('[data-cancel]').trigger('click');
        expect(w.emitted('cancel')).toBeTruthy();
    });
    it('busy 時確認鈕停用', () => {
        const w = factory({ busy: true });
        expect(w.get('[data-confirm]').attributes('disabled')).toBeDefined();
    });
});
