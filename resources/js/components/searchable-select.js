function initSearchableSelect(root) {
    if (root.dataset.ssInitialized === '1') return;
    root.dataset.ssInitialized = '1';

    const hiddenInput = root.querySelector('input[type="hidden"]');
    const textInput = root.querySelector('.ss-input');
    const dropdown = root.querySelector('.ss-dropdown');
    const options = root.querySelectorAll('.ss-option');
    const groups = root.querySelectorAll('.ss-group');
    const noResults = root.querySelector('.ss-no-results');

    function showDropdown() {
        dropdown.classList.remove('hidden');
    }

    function hideDropdown() {
        dropdown.classList.add('hidden');
    }

    function filter() {
        const q = textInput.value.toLowerCase().trim();
        let hasAny = false;

        groups.forEach((group) => {
            const moduleLabel = group.dataset.module.toLowerCase();
            const groupOptions = group.querySelectorAll('.ss-option');
            let groupHasVisible = false;

            groupOptions.forEach((opt) => {
                const match = !q || opt.dataset.search.includes(q) || moduleLabel.includes(q);
                opt.classList.toggle('hidden', !match);
                if (match) {
                    groupHasVisible = true;
                    hasAny = true;
                }
            });

            group.classList.toggle('hidden', !groupHasVisible);
        });

        noResults.classList.toggle('hidden', hasAny);
    }

    textInput.addEventListener('focus', () => {
        textInput.select();
        showDropdown();
    });

    textInput.addEventListener('input', () => {
        hiddenInput.value = '';
        filter();
        showDropdown();
    });

    options.forEach((opt) => {
        opt.addEventListener('click', () => {
            hiddenInput.value = opt.dataset.value;
            textInput.value = opt.dataset.label;

            options.forEach((o) => {
                o.classList.remove('bg-blue-50', 'text-blue-700', 'font-medium');
            });
            opt.classList.add('bg-blue-50', 'text-blue-700', 'font-medium');

            hideDropdown();
        });
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) {
            hideDropdown();
            if (!hiddenInput.value && textInput.value) {
                textInput.value = '';
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);
});
