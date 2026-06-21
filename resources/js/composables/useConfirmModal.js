import { ref } from 'vue';

export function useConfirmModal() {
    const open = ref(false);
    const busy = ref(false);
    const target = ref(null);
    const show = (t) => { target.value = t; open.value = true; };
    const close = () => { open.value = false; busy.value = false; target.value = null; };
    return { open, busy, target, show, close };
}
