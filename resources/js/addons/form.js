(function () {
    const input = document.getElementById('image');
    const preview = document.getElementById('image-preview');
    const holder = document.getElementById('image-placeholder');
    const overlay = document.getElementById('image-overlay');
    const filename = document.getElementById('image-filename');
    const removeBtn = document.getElementById('image-remove-btn');
    const removeFlag = document.getElementById('remove_image');
    if (!input) return;

    const hadInitialImage = preview && !preview.classList.contains('hidden') && preview.getAttribute('src');
    const isPendingRemove = removeFlag && removeFlag.value === '1';

    // 驗證失敗重渲染：remove_image=1 時把 UI 還原成「已刪除」狀態
    if (hadInitialImage && isPendingRemove) {
        preview.src = '';
        preview.classList.add('hidden');
        overlay.classList.add('hidden');
        holder.classList.remove('hidden');
    }

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        filename.textContent = file.name;
        if (removeFlag) removeFlag.value = '0';

        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            holder.classList.add('hidden');
            overlay.classList.remove('hidden');
            if (removeBtn) removeBtn.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (removeFlag) removeFlag.value = '1';
            input.value = '';
            filename.textContent = '';
            preview.src = '';
            preview.classList.add('hidden');
            overlay.classList.add('hidden');
            holder.classList.remove('hidden');
            removeBtn.classList.add('hidden');
        });
    }

    // 無圖（建立頁、編輯但無圖）或已待刪：隱藏刪除按鈕
    if ((!hadInitialImage || isPendingRemove) && removeBtn) {
        removeBtn.classList.add('hidden');
    }
})();
