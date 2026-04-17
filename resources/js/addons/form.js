(function () {
    const input = document.getElementById('image');
    const preview = document.getElementById('image-preview');
    const holder = document.getElementById('image-placeholder');
    const overlay = document.getElementById('image-overlay');
    const filename = document.getElementById('image-filename');
    if (!input) return;

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        filename.textContent = file.name;

        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            holder.classList.add('hidden');
            overlay.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
})();
