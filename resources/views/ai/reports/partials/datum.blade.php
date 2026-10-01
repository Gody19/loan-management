@php
    /**
     * One classified datum. The classification badge is never optional: a fact,
     * a trend, a prediction and an advisory must never be visually
     * interchangeable to the reader.
     */
    $classificationMeta = [
        'fact' => ['label' => 'Fact', 'class' => 'bg-primary', 'icon' => 'bi-clipboard-data'],
        'trend' => ['label' => 'Trend', 'class' => 'bg-info text-dark', 'icon' => 'bi-graph-up-arrow'],
        'prediction' => ['label' => 'Prediction', 'class' => 'bg-warning text-dark', 'icon' => 'bi-graph-up'],
        'advisory' => ['label' => 'Advisory', 'class' => 'bg-secondary', 'icon' => 'bi-lightbulb'],
    ];

    $meta = $classificationMeta[$datum['classification'] ?? 'fact'] ?? $classificationMeta['fact'];

    // The persisted meta payload is a JSON array, so normalize it once to an
    // object for readable optional access below.
    $details = (object) (($datum['meta'] ?? []) ?: []);
@endphp

<div class="border rounded p-2 mb-2">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="fw-semibold">{{ $datum['label'] ?? 'Untitled' }}</div>
        <span class="badge {{ $meta['class'] }}">
            <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
        </span>
    </div>

    @if (array_key_exists('value', $datum) && $datum['value'] !== null)
        <div class="fs-6 mt-1">
            @php
                $value = $datum['value'];
                $format = $datum['format'] ?? 'text';
            @endphp
            @if ($format === 'money')
                {{ config('intelligence-reporting.currency', 'TZS') }} {{ number_format((float) $value, 2) }}
            @elseif ($format === 'percent')
                {{ number_format((float) $value, 2) }}%
            @elseif ($format === 'integer')
                {{ number_format((float) $value) }}
            @elseif ($format === 'decimal')
                {{ number_format((float) $value, 2) }}
            @elseif ($format === 'boolean')
                <span class="badge {{ $value ? 'bg-success' : 'bg-secondary' }}">{{ $value ? 'Yes' : 'No' }}</span>
            @elseif ($format === 'date')
                {{ \Carbon\CarbonImmutable::parse($value)->toFormattedDateString() }}
            @else
                {{ $value }}
            @endif
        </div>
    @endif

    @if (($datum['classification'] ?? '') === 'trend')
        <div class="small mt-1 text-muted">
            @if (($datum['direction'] ?? null) === null || ($datum['direction'] ?? '') === 'unavailable')
                <i class="bi bi-dash-circle me-1"></i>No previous comparable period data — the comparison could not be calculated.
            @else
                <i class="bi bi-arrow-{{ ($datum['direction'] ?? '') === 'down' ? 'down' : (($datum['direction'] ?? '') === 'up' ? 'up' : 'right') }} me-1"></i>
                {{ ucfirst((string) ($datum['direction_label'] ?? $datum['direction'])) }}
                @if ($datum['absolute_change'] !== null)
                    &middot; change {{ number_format((float) $datum['absolute_change'], 2) }}
                @endif
                @if ($datum['percentage_change'] !== null)
                    ({{ number_format((float) $datum['percentage_change'], 2) }}%)
                @endif
                @if ($datum['previous_value'] !== null)
                    &middot; previous {{ number_format((float) $datum['previous_value'], 2) }}
                @endif
            @endif
        </div>
    @endif

    @if (($datum['classification'] ?? '') === 'prediction')
        <div class="small mt-1">
            <div>
                <span class="badge bg-light text-dark">{{ ucfirst((string) ($details->status_label ?? 'unknown')) }}</span>
                <span class="badge bg-light text-dark">{{ ucfirst((string) ($details->confidence ?? 'n/a')) }} confidence</span>
                <span class="badge bg-light text-dark">{{ ucfirst((string) ($details->data_quality ?? 'n/a')) }} data</span>
            </div>
            @if (! empty($details->value) || (isset($details->value) && $details->value !== null))
                <div class="mt-1">
                    Outlook value:
                    <strong>
                        @if (($details->value ?? null) !== null)
                            {{ config('intelligence-reporting.currency', 'TZS') }} {{ number_format((float) $details->value, 2) }}
                        @else
                            n/a
                        @endif
                    </strong>
                    over {{ $details->horizon ?? 'the forecast horizon' }}
                </div>
            @endif
            @if (! empty($details->explanation))
                <div class="text-muted mt-1">{{ $details->explanation }}</div>
            @endif
        </div>
    @endif

    @if (($datum['classification'] ?? '') === 'advisory')
        <div class="small mt-1">
            <div>
                <span class="badge bg-{{ $details->severity ?? 'secondary' }}">{{ ucfirst((string) ($details->severity_label ?? 'unknown')) }}</span>
                <span class="badge bg-light text-dark">{{ ucfirst((string) ($details->type_label ?? 'insight')) }}</span>
                <span class="badge bg-light text-dark">{{ ucfirst((string) ($details->status_label ?? 'open')) }}</span>
            </div>
            @if (! empty($details->summary))
                <div class="mt-1">{{ $details->summary }}</div>
            @endif
            @if (! empty($details->recommendation))
                <div class="text-muted mt-1"><strong>Suggested:</strong> {{ $details->recommendation }}</div>
            @endif
            <div class="text-muted mt-1 fst-italic">{{ $details->lifecycle ?? 'This is a human decision on the dashboard.' }}</div>
        </div>
    @endif

    @if (! empty($datum['note']))
        <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>{{ $datum['note'] }}</div>
    @endif

    <div class="small text-muted mt-1 fst-italic">Source: {{ $datum['source'] ?? 'FinancePro' }}</div>
</div>