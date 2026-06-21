import mountIsland from '@/lib/mountIsland';
import RowDeleteController from '@/components/RowDeleteController.vue';
import { useFlash } from '@/composables/useFlash';

useFlash().autoDismissFlashes();
mountIsland('row-delete', RowDeleteController);
