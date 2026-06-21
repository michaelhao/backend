import mountIsland from '@/lib/mountIsland';
import RowDeleteController from '@/components/RowDeleteController.vue';
import { useFlash } from '@/composables/useFlash';

useFlash().autoDismissFlashes();
mountIsland('row-delete', RowDeleteController);

document.getElementById('per-page-select')?.addEventListener('change', () => {
    document.getElementById('per-page-form').submit();
});
