<script setup>
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '確認' },
    name: { type: String, default: '' },
    actionLabel: { type: String, default: '確認' },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);

const onEsc = (e) => { if (e.key === 'Escape') { emit('cancel'); } };
watch(() => props.open, (open) => {
    if (open) { document.addEventListener('keydown', onEsc); }
    else { document.removeEventListener('keydown', onEsc); }
});
onBeforeUnmount(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
  <div v-if="open" class="modal-overlay" @click.self="emit('cancel')">
    <div class="modal-panel">
      <h3 class="text-lg font-medium mb-2">{{ title }}</h3>
      <p class="mb-4 text-gray-600">{{ name }}</p>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" data-cancel @click="emit('cancel')">取消</button>
        <button type="button" class="btn-primary" data-confirm :disabled="busy" @click="emit('confirm')">
          {{ busy ? '處理中…' : actionLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
