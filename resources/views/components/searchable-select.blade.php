@props(['name', 'value' => '', 'permissions', 'placeholder' => '搜尋或選擇頁面...'])

@php
    $ssGroups = collect($permissions)->map(function ($modulePermissions, $module) {
        $first = $modulePermissions->first();
        $moduleLabel = $first->description ? explode(' - ', $first->description)[0] : $module;
        return [
            'module' => $moduleLabel,
            'options' => $modulePermissions->map(function ($p) use ($moduleLabel) {
                $label = $p->description ?? $p->name;
                $action = $p->description ? (explode(' - ', $p->description)[1] ?? $p->action) : $p->action;
                return [
                    'value' => $p->name,
                    'label' => $label,
                    'action' => $action,
                    'search' => mb_strtolower($label . ' ' . $moduleLabel . ' ' . $p->name),
                ];
            })->values(),
        ];
    })->values();

    $ssProps = ['name' => $name, 'value' => $value, 'placeholder' => $placeholder, 'groups' => $ssGroups];
@endphp

<div class="ss-island" data-props='@json($ssProps)'></div>

@once
    @push('scripts')
        @vite('resources/js/components/searchable-select.js')
    @endpush
@endonce
