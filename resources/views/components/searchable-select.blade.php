@props(['name', 'value' => '', 'permissions', 'placeholder' => '搜尋或選擇頁面...'])

@php
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

<div class="relative" data-searchable-select>
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

@once
    @push('scripts')
        @vite('resources/js/components/searchable-select.js')
    @endpush
@endonce
