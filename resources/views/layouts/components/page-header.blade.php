{{-- Page Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-semibold">{{ $title ?? '' }}</h1>
        @isset($subtitle)
            <p class="text-muted mb-0" style="font-size: 0.875rem;">{{ $subtitle }}</p>
        @endisset
    </div>
    <div class="d-flex gap-2">
        {!! $actions ?? '' !!}
    </div>
</div>
