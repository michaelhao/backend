import { autoDismissFlashes } from '../utils/flash.js';
import { initDeleteModal } from '../utils/deleteModal.js';

document.addEventListener('DOMContentLoaded', () => {
    autoDismissFlashes();
    initDeleteModal();
});
