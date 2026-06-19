<script setup>
import { ref, onMounted } from 'vue';

const open = ref(false);
const businessNumber = ref('');
const companyName = ref('');

onMounted(() => {
    document.querySelectorAll('.cert-badge').forEach((badge) => {
        badge.addEventListener('click', () => {
            businessNumber.value = badge.dataset.businessNumber;
            companyName.value = badge.dataset.companyName || '-';
            open.value = true;
        });
    });
});
</script>

<template>
  <div v-if="open" class="modal-overlay" @click.self="open = false">
    <div class="modal-panel">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">認證資訊</h3>
      <div class="space-y-3 text-sm">
        <div class="flex gap-2">
          <span class="text-gray-500 w-24 flex-shrink-0">統一編號：</span>
          <span class="text-gray-800 font-mono">{{ businessNumber }}</span>
        </div>
        <div class="flex gap-2">
          <span class="text-gray-500 w-24 flex-shrink-0">公司名稱：</span>
          <span class="text-gray-800">{{ companyName }}</span>
        </div>
      </div>
      <div class="mt-6 flex justify-end">
        <button type="button"
                class="btn-cancel"
                data-close
                @click="open = false">
          關閉
        </button>
      </div>
    </div>
  </div>
</template>
