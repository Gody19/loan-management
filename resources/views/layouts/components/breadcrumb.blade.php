{{-- Breadcrumb --}}
@if(isset($breadcrumbs) && count($breadcrumbs))
<nav aria-label="breadcrumb">
    <ol class="breadcrumb vicoba-breadcrumb">
        @foreach($breadcrumbs as $item)
            @php
                $label = is_array($item) ? ($item['label'] ?? '') : $item;
                $url = is_array($item) ? ($item['url'] ?? null) : null;
            @endphp
            @if($loop->last && !$url)
                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
@endif
