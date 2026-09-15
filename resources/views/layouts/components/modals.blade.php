{{-- Reusable Modal Component --}}
@props([
    'id',
    'title' => '',
    'size' => '',
    'static' => false,
])

@php
    $sizeClass = match($size) {
        'sm' => 'modal-sm',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        default => '',
    };

    $backdrop = $static ? 'static' : 'true';
    $keyboard = $static ? 'false' : 'true';
@endphp

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true"
     data-bs-backdrop="{{ $backdrop }}" data-bs-keyboard="{{ $keyboard }}">
    <div class="modal-dialog {{ $sizeClass }}">
        <div class="modal-content">
            @if($title)
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="{{ $id }}Label">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            @endif

            <div class="modal-body">
                {{ $slot }}
            </div>

            @isset($footer)
            <div class="modal-footer">
                {{ $footer }}
            </div>
            @endisset
        </div>
    </div>
</div>
