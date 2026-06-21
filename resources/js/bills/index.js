import mountIsland from '@/lib/mountIsland';
import BillsListModals from './BillsListModals.vue';
import { useFlash } from '@/composables/useFlash';

useFlash().autoDismissFlashes();
mountIsland('bills-modals', BillsListModals);
