import { autoDismissFlashes } from '../utils/flash.js';

document.addEventListener('DOMContentLoaded', () => {
    autoDismissFlashes();

    document.getElementById('per-page-select')?.addEventListener('change', () => {
        document.getElementById('per-page-form').submit();
    });

    initCertBadgeModal();
});

function initCertBadgeModal() {
    const modal = document.getElementById('cert-modal');
    if (!modal) return;

    const modalBusinessNumber = document.getElementById('modal-business-number');
    const modalCompanyName = document.getElementById('modal-company-name');

    document.querySelectorAll('.cert-badge').forEach((badge) => {
        badge.addEventListener('click', function () {
            modalBusinessNumber.textContent = this.dataset.businessNumber;
            modalCompanyName.textContent = this.dataset.companyName || '-';
            modal.classList.remove('hidden');
        });
    });

    document.getElementById('cert-modal-close')?.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });
}
