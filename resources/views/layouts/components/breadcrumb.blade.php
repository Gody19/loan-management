{{-- Breadcrumb --}}
<nav aria-label="breadcrumb">
    <ol class="breadcrumb vicoba-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" style="text-decoration: none;">Dashboard</a></li>
        @foreach($breadcrumbs as $item)
            @php
                $label = is_array($item) ? ($item['label'] ?? '') : $item;
                $url = is_array($item) ? ($item['url'] ?? null) : null;
            @endphp
            @if($loop->last && !$url)
                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $url }}" style="text-decoration: none;">{{ $label }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
