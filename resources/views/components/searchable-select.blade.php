@props(['name', 'value' => '', 'permissions', 'placeholder' => '搜尋或選擇頁面...'])

@php
    $componentId = 'ss-' . str_replace(['.', ' '], '-', $name) . '-' . uniqid();
    $selectedLabel = '';
    if ($value) {
        foreach ($permissions as $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                if ($permission->name === $value) {
                    $selectedLabel = $permission->description ?? $permission->name;
                    break 2;
                }
            }
        }
    }
@endphp

<div class="relative" id="{{ $componentId }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    <input type="text"
           value="{{ $selectedLabel }}"
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm ss-input">

    <div class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden ss-dropdown">
        @foreach ($permissions as $module => $modulePermissions)
            @php
                $moduleLabel = $modulePermissions->first()->description
                    ? explode(' - ', $modulePermissions->first()->description)[0]
                    : $module;
            @endphp
            <div class="ss-group" data-module="{{ $moduleLabel }}">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase bg-gray-50 sticky top-0 ss-group-label">
                    {{ $moduleLabel }}
                </div>
                @foreach ($modulePermissions as $permission)
                    @php
                        $actionLabel = $permission->description
                            ? (explode(' - ', $permission->description)[1] ?? $permission->action)
                            : $permission->action;
                        $fullLabel = $permission->description ?? $permission->name;
                    @endphp
                    <button type="button"
                            data-value="{{ $permission->name }}"
                            data-label="{{ $fullLabel }}"
                            data-search="{{ mb_strtolower($fullLabel . ' ' . $moduleLabel . ' ' . $permission->name) }}"
                            class="w-full text-left px-3 py-2 pl-6 text-sm text-gray-700 hover:bg-blue-50 transition-colors ss-option {{ $permission->name === $value ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                        {{ $actionLabel }}
                    </button>
                @endforeach
            </div>
        @endforeach
        <div class="px-3 py-2 text-sm text-gray-400 hidden ss-no-results">無符合結果</div>
    </div>
</div>

<script>
    (function () {
        const root = document.getElementById('{{ $componentId }}');
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

            groups.forEach(group => {
                const moduleLabel = group.dataset.module.toLowerCase();
                const groupOptions = group.querySelectorAll('.ss-option');
                let groupHasVisible = false;

                groupOptions.forEach(opt => {
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

        options.forEach(opt => {
            opt.addEventListener('click', () => {
                hiddenInput.value = opt.dataset.value;
                textInput.value = opt.dataset.label;

                options.forEach(o => {
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
    })();
</script>
