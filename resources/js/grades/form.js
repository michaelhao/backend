import mountIsland from '@/lib/mountIsland';
import GradeWeightField from './GradeWeightField.vue';

const submitBtn = document.querySelector('#grade-weight-field')?.closest('form')?.querySelector('button[type="submit"]');

mountIsland('grade-weight-field', GradeWeightField, {
    'onUpdate:disabled': (disabled) => {
        if (!submitBtn) { return; }
        submitBtn.disabled = disabled;
        submitBtn.classList.toggle('opacity-50', disabled);
        submitBtn.classList.toggle('cursor-not-allowed', disabled);
    },
});
