import { useFlash } from '@/composables/useFlash';

document.addEventListener('DOMContentLoaded', () => {
    useFlash().autoDismissFlashes();
});
