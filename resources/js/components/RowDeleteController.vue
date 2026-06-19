<script setup>
import { onMounted } from 'vue';
import http from '@/lib/http';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { useConfirmModal } from '@/composables/useConfirmModal';
import { useFlash } from '@/composables/useFlash';

defineProps({
    title: { type: String, default: '確認刪除' },
    actionLabel: { type: String, default: '確認刪除' },
});

const { open, busy, target, show, close } = useConfirmModal();
const { showFlash } = useFlash();

onMounted(() => {
    document.querySelectorAll('.delete-btn').forEach((btn) => {
        btn.addEventListener('click', () => show({ url: btn.dataset.url, name: btn.dataset.name, btn }));
    });
});

const confirm = async () => {
    const url = target.value?.url;
    if (!url) { return; }
    busy.value = true;
    try {
        const res = await http.delete(url);
        target.value?.btn?.closest('tr')?.remove();
        showFlash('success', res.data?.message ?? '已成功刪除');
    } catch (err) {
        showFlash('error', err.response?.data?.message ?? '刪除失敗，請稍後再試');
    } finally {
        close();
    }
};
</script>

<template>
  <ConfirmModal
    :open="open"
    :title="title"
    :name="target?.name ?? ''"
    :action-label="actionLabel"
    :busy="busy"
    @confirm="confirm"
    @cancel="close"
  />
</template>
