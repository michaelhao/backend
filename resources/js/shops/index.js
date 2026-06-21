import mountIsland from '@/lib/mountIsland';
import CertBadgeModal from './CertBadgeModal.vue';
import { useFlash } from '@/composables/useFlash';

useFlash().autoDismissFlashes();

document.getElementById('per-page-select')?.addEventListener('change', () => {
    document.getElementById('per-page-form').submit();
});

mountIsland('cert-badge-modal', CertBadgeModal);
