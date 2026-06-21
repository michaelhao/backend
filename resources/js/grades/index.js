import mountIsland from '@/lib/mountIsland';
import GradeToggleController from './GradeToggleController.vue';
import { useFlash } from '@/composables/useFlash';
useFlash().autoDismissFlashes();
mountIsland('grade-toggle', GradeToggleController);
