@props([
    'name',
    'id' => null,
])

<select
    name="{{ $name }}"
    id="{{ $id ?? $name }}"
    {{ $attributes->merge(['class' => 'form-control']) }}>
    {{ $slot }}
</select>
