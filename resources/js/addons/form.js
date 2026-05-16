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

    // 若一開始無圖（建立頁或編輯但無圖），確保刪除按鈕保持隱藏
    if (!hadInitialImage && removeBtn) {
        removeBtn.classList.add('hidden');
    }
})();
