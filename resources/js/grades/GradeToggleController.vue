<script setup>
import { onMounted } from 'vue';
import http from '@/lib/http';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { useConfirmModal } from '@/composables/useConfirmModal';
import { useFlash } from '@/composables/useFlash';

const { open, busy, target, show, close } = useConfirmModal();
const { showFlash } = useFlash();

onMounted(() => {
    document.querySelectorAll('.toggle-btn').forEach((btn) => {
        btn.addEventListener('click', () => show(btn));
    });
});

const actionLabel = () => {
    if (!target.value) { return '確認'; }
    return target.value.dataset.active === '1' ? '停用' : '啟用';
};

const modalTitle = () => {
    if (!target.value) { return '確認'; }
    const action = target.value.dataset.active === '1' ? '停用' : '啟用';
    return `確定要${action}嗎?`;
};

const confirm = async () => {
    const btn = target.value;
    if (!btn) { return; }
    busy.value = true;
    try {
        await http.patch(btn.dataset.url);
        const isActive = btn.dataset.active === '1';
        btn.dataset.active = isActive ? '0' : '1';
        btn.classList.toggle('bg-green-500', !isActive);
        btn.classList.toggle('bg-gray-300', isActive);
        const dot = btn.querySelector('span');
        dot.classList.toggle('translate-x-6', !isActive);
        dot.classList.toggle('translate-x-1', isActive);
        btn.title = !isActive ? '點擊關閉' : '點擊啟用';
        showFlash('success', '版本狀態已更新');
    } catch (err) {
        showFlash('error', err.response?.data?.message ?? '操作失敗，請稍後再試');
    } finally {
        close();
    }
};
</script>

<template>
  <ConfirmModal
    :open="open"
    :title="modalTitle()"
    :name="target?.dataset.name ?? ''"
    :action-label="actionLabel()"
    :busy="busy"
    @confirm="confirm"
    @cancel="close"
  />
</template>
