{{--
    Branded print header used by every printed document.

    Params:
      $organization  string  organisation name shown on the right
      $meta          array   additional right-aligned lines (branch, generated date, ...)
      $title         string  document title rendered under the header row
--}}
<header class="print-doc-header">
    <div class="print-doc-header-row">
        <div class="print-brand">
            <img class="print-brand-mark" src="{{ asset('images/financepro-mark.svg') }}" alt="">
            <div class="print-brand-text">
                <strong>{{ config('app.name', 'FinancePro') }}</strong>
                <span>VICOBA System</span>
            </div>
        </div>
        <div class="print-doc-meta">
            @if (!empty($organization))
                <div class="print-doc-org">{{ $organization }}</div>
            @endif
            @foreach (($meta ?? []) as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>
    </div>
    @if (!empty($title))
        <h1 class="print-doc-title">{{ $title }}</h1>
    @endif
</header>
