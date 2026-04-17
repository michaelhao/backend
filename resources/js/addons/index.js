import { showFlash, autoDismissFlashes } from '../utils/flash.js';

document.addEventListener('DOMContentLoaded', () => {
    autoDismissFlashes();

    document.getElementById('per-page-select')?.addEventListener('change', () => {
        document.getElementById('per-page-form').submit();
    });

    initDeleteModal();
});

function initDeleteModal() {
    let deleteTargetUrl = null;
    const modal = document.getElementById('delete-modal');
    const cancelBtn = document.getElementById('delete-modal-cancel');
    const confirmBtn = document.getElementById('delete-modal-confirm');
    if (!modal || !cancelBtn || !confirmBtn) return;

    document.querySelectorAll('.delete-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            deleteTargetUrl = this.dataset.url;
            document.getElementById('delete-modal-name').textContent = this.dataset.name;
            modal.classList.remove('hidden');
        });
    });

    cancelBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        deleteTargetUrl = null;
    });

    confirmBtn.addEventListener('click', async function () {
        if (!deleteTargetUrl) return;

        this.disabled = true;
        this.textContent = '刪除中...';

        try {
            await window.axios.delete(deleteTargetUrl);
            document.querySelector(`[data-url="${deleteTargetUrl}"]`).closest('tr').remove();
            showFlash('success', '已成功刪除');
        } catch (err) {
            const message = err.response?.data?.message ?? '刪除失敗，請稍後再試';
            showFlash('error', message);
        } finally {
            modal.classList.add('hidden');
            this.disabled = false;
            this.textContent = '確認刪除';
            deleteTargetUrl = null;
        }
    });
}
