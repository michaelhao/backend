import { showFlash } from './flash.js';

/**
 * 綁定列表頁的「axios DELETE + 確認 modal」互動。
 *
 * 依賴頁面既有的共用標記（各頁自維護 HTML）：
 * - `.delete-btn` 按鈕帶 `data-url`（DELETE 目標）與 `data-name`（顯示名稱）
 * - `#delete-modal` 全螢幕 overlay，含 `#delete-modal-name`、`#delete-modal-cancel`、`#delete-modal-confirm`
 * - `.flash-area` 供 flash 訊息插入
 *
 * 成功時移除對應 `<tr>` 並以 server 回傳的 message 顯示 flash。
 */
export function initDeleteModal() {
    let deleteTargetUrl = null;
    const modal = document.getElementById('delete-modal');
    const cancelBtn = document.getElementById('delete-modal-cancel');
    const confirmBtn = document.getElementById('delete-modal-confirm');
    if (!modal || !cancelBtn || !confirmBtn) return;

    function onEsc(e) {
        if (e.key === 'Escape') closeModal();
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.removeEventListener('keydown', onEsc);
        deleteTargetUrl = null;
    }

    document.querySelectorAll('.delete-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            deleteTargetUrl = this.dataset.url;
            document.getElementById('delete-modal-name').textContent = this.dataset.name;
            modal.classList.remove('hidden');
            document.addEventListener('keydown', onEsc);
        });
    });

    cancelBtn.addEventListener('click', closeModal);

    // 點 overlay 背景（非內層卡片）關閉
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    confirmBtn.addEventListener('click', async function () {
        const url = deleteTargetUrl;
        if (!url) return;

        this.disabled = true;
        this.textContent = '刪除中...';

        try {
            const res = await window.axios.delete(url);
            document.querySelector(`[data-url="${url}"]`).closest('tr').remove();
            showFlash('success', res.data?.message ?? '已成功刪除');
        } catch (err) {
            const message = err.response?.data?.message ?? '刪除失敗，請稍後再試';
            showFlash('error', message);
        } finally {
            closeModal();
            this.disabled = false;
            this.textContent = '確認刪除';
        }
    });
}
