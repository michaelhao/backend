<script setup>
import { onMounted, onBeforeUnmount } from 'vue';

// Element refs for cleanup
let input = null;
let preview = null;
let holder = null;
let overlay = null;
let filename = null;
let removeBtn = null;
let removeFlag = null;

// Named listener refs for cleanup
let onFileChange = null;
let onRemoveClick = null;

onMounted(() => {
    input = document.getElementById('image');
    preview = document.getElementById('image-preview');
    holder = document.getElementById('image-placeholder');
    overlay = document.getElementById('image-overlay');
    filename = document.getElementById('image-filename');
    removeBtn = document.getElementById('image-remove-btn');
    removeFlag = document.getElementById('remove_image');

    if (!input) return;

    const hadInitialImage = preview && !preview.classList.contains('hidden') && preview.getAttribute('src');
    const isPendingRemove = removeFlag && removeFlag.value === '1';

    // 驗證失敗重渲染：remove_image=1 時把 UI 還原成「已刪除」狀態
    if (hadInitialImage && isPendingRemove) {
        preview.src = '';
        preview.classList.add('hidden');
        if (overlay) overlay.classList.add('hidden');
        if (holder) holder.classList.remove('hidden');
    }

    // 無圖或已待刪：隱藏刪除按鈕
    if ((!hadInitialImage || isPendingRemove) && removeBtn) {
        removeBtn.classList.add('hidden');
    }

    onFileChange = function () {
        const file = this.files[0];
        if (!file) return;

        if (filename) filename.textContent = file.name;
        if (removeFlag) removeFlag.value = '0';

        const reader = new FileReader();
        reader.onload = (e) => {
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            if (holder) holder.classList.add('hidden');
            if (overlay) overlay.classList.remove('hidden');
            if (removeBtn) removeBtn.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    };
    input.addEventListener('change', onFileChange);

    if (removeBtn) {
        onRemoveClick = function () {
            if (removeFlag) removeFlag.value = '1';
            input.value = '';
            if (filename) filename.textContent = '';
            if (preview) {
                preview.src = '';
                preview.classList.add('hidden');
            }
            if (overlay) overlay.classList.add('hidden');
            if (holder) holder.classList.remove('hidden');
            removeBtn.classList.add('hidden');
        };
        removeBtn.addEventListener('click', onRemoveClick);
    }
});

onBeforeUnmount(() => {
    if (input && onFileChange) {
        input.removeEventListener('change', onFileChange);
    }
    if (removeBtn && onRemoveClick) {
        removeBtn.removeEventListener('click', onRemoveClick);
    }
});
</script>

<template>
  <span></span>
</template>
