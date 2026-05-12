@props([
    'name' => null,
    'id' => null,
    'type' => 'text',
    'value' => null,
])

@php
    $resolvedId = $id ?? $name;
@endphp

<input
    type="{{ $type }}"
    @if (! is_null($name)) name="{{ $name }}" @endif
    @if (! is_null($resolvedId)) id="{{ $resolvedId }}" @endif
    value="{{ $value }}"
    {{ $attributes->merge(['class' => 'form-control']) }}>
