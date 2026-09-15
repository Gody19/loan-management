{{-- Reusable Button Component --}}
@props([
    'variant' => 'primary',
    'size' => '',
    'icon' => '',
    'type' => 'button',
    'disabled' => false,
    'href' => '',
])

@php
    $classes = match($variant) {
        'primary' => 'btn btn-primary',
        'secondary' => 'btn btn-secondary',
        'success' => 'btn btn-success',
        'danger' => 'btn btn-danger',
        'warning' => 'btn btn-warning',
        'info' => 'btn btn-info',
        'light' => 'btn btn-light',
        'dark' => 'btn btn-dark',
        'outline-primary' => 'btn btn-outline-primary',
        'outline-secondary' => 'btn btn-outline-secondary',
        'outline-success' => 'btn btn-outline-success',
        'outline-danger' => 'btn btn-outline-danger',
        'link' => 'btn btn-link',
        default => 'btn btn-primary',
    };

    $sizeClass = match($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };

    $disabledAttr = $disabled ? 'disabled' : '';
@endphp

@if($href)
    <a href="{{ $href }}" class="vicoba-btn {{ $classes }} {{ $sizeClass }}" {{ $disabledAttr }}>
        @if($icon)
            <i class="{{ $icon }} me-1"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" class="vicoba-btn {{ $classes }} {{ $sizeClass }}" {{ $disabledAttr }}>
        @if($icon)
            <i class="{{ $icon }} me-1"></i>
        @endif
        {{ $slot }}
    </button>
@endif
