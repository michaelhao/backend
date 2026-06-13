import { autoDismissFlashes } from '../utils/flash.js';
import { initDeleteModal } from '../utils/deleteModal.js';

document.addEventListener('DOMContentLoaded', () => {
    autoDismissFlashes();

    document.getElementById('per-page-select')?.addEventListener('change', () => {
        document.getElementById('per-page-form').submit();
    });

    initDeleteModal();
});
