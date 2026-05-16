@props([
    'name' => null,
    'id' => null,
])

@php
    $resolvedId = $id ?? $name;
@endphp

<select
    @if (! is_null($name)) name="{{ $name }}" @endif
    @if (! is_null($resolvedId)) id="{{ $resolvedId }}" @endif
    {{ $attributes->merge(['class' => 'form-control']) }}>
    {{ $slot }}
</select>
