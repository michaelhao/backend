import { showFlash, autoDismissFlashes } from '../utils/flash.js';

document.addEventListener('DOMContentLoaded', () => {
    autoDismissFlashes();
    initToggleModal();
});

function initToggleModal() {
    let toggleTargetBtn = null;
    const modal = document.getElementById('toggle-modal');
    const cancelBtn = document.getElementById('toggle-modal-cancel');
    const confirmBtn = document.getElementById('toggle-modal-confirm');
    if (!modal || !cancelBtn || !confirmBtn) return;

    document.querySelectorAll('.toggle-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            toggleTargetBtn = this;
            const isActive = this.dataset.active === '1';
            document.getElementById('toggle-modal-action').textContent = isActive ? '停用' : '啟用';
            document.getElementById('toggle-modal-name').textContent = this.dataset.name;
            modal.classList.remove('hidden');
        });
    });

    cancelBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        toggleTargetBtn = null;
    });

    confirmBtn.addEventListener('click', async function () {
        if (!toggleTargetBtn) return;
        this.disabled = true;
        this.textContent = '處理中...';

        try {
            await window.axios.patch(toggleTargetBtn.dataset.url);
            const isActive = toggleTargetBtn.dataset.active === '1';
            toggleTargetBtn.dataset.active = isActive ? '0' : '1';
            toggleTargetBtn.classList.toggle('bg-green-500', !isActive);
            toggleTargetBtn.classList.toggle('bg-gray-300', isActive);
            const dot = toggleTargetBtn.querySelector('span');
            dot.classList.toggle('translate-x-6', !isActive);
            dot.classList.toggle('translate-x-1', isActive);
            toggleTargetBtn.title = !isActive ? '點擊關閉' : '點擊啟用';
            showFlash('success', '版本狀態已更新');
        } catch (err) {
            const message = err.response?.data?.message ?? '操作失敗，請稍後再試';
            showFlash('error', message);
        } finally {
            modal.classList.add('hidden');
            this.disabled = false;
            this.textContent = '確認';
            toggleTargetBtn = null;
        }
    });
}