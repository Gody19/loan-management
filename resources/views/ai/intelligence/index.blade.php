@extends('layouts.app')

@section('title', 'Financial Intelligence - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Financial Intelligence',
        'subtitle' => 'Descriptive, read-only insight into your authorized portfolio, collections, accounting and anomalies.',
    ])
@endsection

@section('content')
    {{--
        Financial Intelligence dashboard (Phase 11.7).

        Read-only only: every figure rendered here is a deterministic summary
        computed from authoritative FinancePro records within the acting user's
        trusted organization/branch scope. No input on this page can change a
        financial record; the anomaly review button only records a human review
        marker.
    --}}

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="vicoba-card">
                <div class="card-header"><i class="bi bi-info-circle me-1"></i> About this report</div>
                <div class="card-body small text-muted">
                    All figures are computed from authoritative FinancePro records within your
                    organization and branch scope as of the current moment. Principal owed at or
                    beyond the {{ config('financial-intelligence.par_threshold_days', 30) }}-day
                    threshold is reported as portfolio-at-risk. This page is read-only and audited.
                </div>
            </div>
        </div>
    </div>

    @if (! isset($portfolio) && ! isset($par) && ! isset($delinquency) && ! isset($collections) && ! isset($trends) && ! isset($accounting) && ! isset($findings) && ! isset($predictive))
        <div class="row g-3">
            <div class="col-12">
                <div class="vicoba-card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-graph-up-arrow d-block mb-2" style="font-size: 2rem;"></i>
                        You do not have permission to view any financial intelligence section.
                    </div>
                </div>
            </div>
        </div>
    @endif

    @isset($predictive)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-graph-up me-1"></i> Predictive outlook <span class="badge bg-warning text-dark ms-2">Advisory</span>
                <form method="POST" action="{{ route('ai.intelligence.predictions.refresh') }}" class="float-end">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                </form>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-3">
                    Statistical indications computed from your organization's historical FinancePro records.
                    They are estimates, not guarantees — they never affect loan decisions, rates or accounting,
                    and they never include member-level detail.
                </div>

                @foreach ($predictive as $domain)
                    <div class="border rounded p-3 mb-3">
                        <div class="fw-bold mb-2">{{ $domain['label'] }}</div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Organization</th>
                                        <th>Status</th>
                                        <th>Quality</th>
                                        <th>Confidence</th>
                                        <th>Data window (from → through)</th>
                                        <th class="text-end">Outlook value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($domain['rows'] as $row)
                                        @php
    $prediction = $row['prediction'];
@endphp
                                        <tr>
                                            <td>{{ $row['organization_name'] }}</td>
                                            @if ($prediction === null)
                                                <td colspan="5" class="text-muted">
                                                    No prediction yet. Run <code>php artisan ai:refresh-predictions --organization={{ $row['organization_id'] }}</code> to generate the first outlook.
                                                </td>
                                            @else
                                                <td>
                                                    <span class="badge bg-{{ $prediction->status->color() }}">{{ $prediction->status->label() }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $prediction->data_quality->color() }}">{{ $prediction->data_quality->label() }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $prediction->confidence->color() }}">{{ $prediction->confidence->label() }}</span>
                                                </td>
                                                <td>{{ $prediction->data_from?->toDateString() ?? '—' }} → {{ $prediction->data_through->toDateString() }}</td>
                                                <td class="text-end">
                                                    {{ $prediction->value_total === null ? '—' : number_format($prediction->value_total, 2) }}
                                                    @if ($prediction->type->value === 'delinquency_risk' && $prediction->value_total !== null)
                                                        <span class="ms-1 small">/ 100</span>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                        @if ($prediction !== null && $prediction->method !== '')
                                            <tr>
                                                <td colspan="6" class="small text-muted">
                                                    <strong>Method:</strong> {{ $prediction->method }}
                                                    &middot; <strong>Horizon:</strong> {{ $prediction->horizon }} month(s)
                                                    &middot; <strong>Observed:</strong> {{ $prediction->assumptions['observation_count'] ?? $prediction->data_quality->value }}
                                                    &middot; <strong>Generated:</strong> {{ $prediction->generated_at?->toDateTimeString() ?? '—' }}
                                                    @if (($prediction->assumptions['incomplete_period_excluded'] ?? null) !== null)
                                                        &middot; In-progress month excluded
                                                    @endif
                                                </td>
                                            </tr>
                                            @if ($prediction->series !== [])
                                                <tr>
                                                    <td colspan="6">
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-borderless mb-0 small">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Period</th>
                                                                        <th class="text-end">Value</th>
                                                                        <th></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @php
                                                                        $valueKey = $prediction->type->value === 'portfolio_forecast' ? 'outstanding_end' : ($prediction->type->value === 'cashflow_forecast' ? 'net' : 'collected');
                                                                    @endphp
                                                                    @foreach ($prediction->series as $point)
                                                                        <tr>
                                                                            <td>{{ $point['period'] }}</td>
                                                                            <td class="text-end">{{ number_format((float) ($point[$valueKey] ?? ($point['value'] ?? 0)), 2) }}</td>
                                                                            <td>
                                                                                @if ($point['is_forecast'])
                                                                                    <span class="badge bg-info text-dark">forecast</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @else
                                                <tr>
                                                    <td colspan="6" class="small text-muted">
                                                        {{ $prediction->explanation }}
                                                    </td>
                                                </tr>
                                            @endif
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endisset

    @isset($portfolio)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> Portfolio summary</div>
            <div class="card-body">
                <div class="row text-center g-3">
                    <div class="col-6 col-md">
                        <div class="text-muted small">Active loans</div>
                        <div class="h4 mb-0">{{ number_format($portfolio->activeLoansCount) }}</div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="text-muted small">Outstanding principal</div>
                        <div class="h4 mb-0">{{ number_format($portfolio->totalOutstanding, 2) }}</div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="text-muted small">Matures in 30 days</div>
                        <div class="h4 mb-0">{{ number_format($portfolio->maturingWithin30Days, 2) }}</div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="text-muted small">Matures in 60 days</div>
                        <div class="h4 mb-0">{{ number_format($portfolio->maturingWithin60Days, 2) }}</div>
                    </div>
                </div>

                @if ($portfolio->compositionByPlan !== [])
                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Loan plan</th>
                                    <th class="text-end">Active</th>
                                    <th class="text-end">Principal outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($portfolio->compositionByPlan as $row)
                                    <tr>
                                        <td>{{ $row['plan'] ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($row['count'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format($row['outstanding'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endisset

    @isset($par)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-exclamation-triangle me-1"></i> Portfolio at risk</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="text-muted small">Outstanding principal</div>
                        <div class="h5 mb-0">{{ number_format($par->totalPrincipalOutstanding, 2) }}</div>
                        <div class="text-muted small mt-2">PAR ({{ $par->parThresholdDays }}+ days)</div>
                        <div class="h5 mb-0">{{ number_format($par->parRateOverThreshold, 2) }}%</div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Aging bucket</th>
                                        <th class="text-end">Loans</th>
                                        <th class="text-end">Principal</th>
                                        <th class="text-end">Percent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($par->buckets as $bucket)
                                        <tr>
                                            <td>{{ $bucket['label'] }}</td>
                                            <td class="text-end">{{ number_format($bucket['loans_count'] ?? 0) }}</td>
                                            <td class="text-end">{{ number_format($bucket['principal_outstanding'] ?? 0, 2) }}</td>
                                            <td class="text-end">{{ number_format($bucket['par_percentage'] ?? 0, 2) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endisset

    @isset($delinquency)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> Delinquency profile</div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    {{ number_format($delinquency->delinquentLoansCount) }} delinquent loan(s),
                    {{ number_format($delinquency->delinquentPrincipalOutstanding, 2) }}{{ $delinquency->currency }}
                    past due; days past due {{ $delinquency->minDpd }}–{{ $delinquency->maxDpd }}.
                </p>
                @if ($delinquency->topDelinquentLoans === [])
                    <p class="text-muted small mb-0">No delinquent loans within scope.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Loan number</th>
                                    <th>Member</th>
                                    <th class="text-end">Days past due</th>
                                    <th>Bucket</th>
                                    <th class="text-end">Principal outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($delinquency->topDelinquentLoans as $loan)
                                    <tr>
                                        <td>{{ $loan['loan_number'] ?? '—' }}</td>
                                        <td>{{ $loan['member_name'] ?? $loan['member_number'] ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($loan['days_past_due'] ?? 0) }}</td>
                                        <td>{{ $loan['bucket_label'] ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($loan['outstanding'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endisset

    @isset($collections)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-cash-coin me-1"></i> Collection summary</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md">
                        <div class="text-muted small">Period due</div>
                        <div class="h5 mb-0">{{ number_format($collections->totalDue, 2) }}</div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="text-muted small">Collected</div>
                        <div class="h5 mb-0">{{ number_format($collections->totalCollected, 2) }}</div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="text-muted small">Collection rate</div>
                        <div class="h5 mb-0">{{ number_format($collections->collectionRate, 2) }}%</div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="text-muted small">Reversal activity</div>
                        <div class="h5 mb-0">{{ number_format($collections->reversedAmount, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endisset

    @isset($trends)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-bar-chart me-1"></i> {{ $trends->months }}-month trend</div>
            <div class="card-body">
                @if ($trends->trend === [])
                    <p class="text-muted small mb-0">No activity to report.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Disbursed</th>
                                    <th class="text-end">Collected</th>
                                    <th class="text-end">Reversals</th>
                                    <th class="text-end">Net cash flow</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trends->trend as $month)
                                    <tr>
                                        <td>{{ $month['label'] ?? $month['period'] ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($month['total_disbursed'] ?? 0, 2) }}</td>
                                        <td class="text-end">{{ number_format($month['total_collected'] ?? 0, 2) }}</td>
                                        <td class="text-end">{{ number_format($month['reversals_count'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format($month['net_cash_flow'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endisset

    @isset($accounting)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-journal-text me-1"></i> Accounting summary</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <div class="text-muted small">Total income</div>
                        <div class="h5 mb-0">{{ number_format($accounting->totalIncome, 2) }}</div>
                        <div class="text-muted small mt-2">Total expenses</div>
                        <div class="h5 mb-0">{{ number_format($accounting->totalExpenses, 2) }}</div>
                        <div class="text-muted small mt-2">Net income</div>
                        <div class="h5 mb-0">{{ number_format($accounting->netIncome, 2) }}</div>
                        <div class="text-muted small mt-2">Trial balance integrity</div>
                        <div class="h5 mb-0">
                            <span class="badge bg-{{ $accounting->allBalanced ? 'success' : 'danger' }}">
                                {{ $accounting->allBalanced ? 'Balanced' : 'Unbalanced' }}
                            </span>
                        </div>
                    </div>
                    <div class="col-12 col-md-7">
                        <div class="text-muted small mb-1">Liquid position</div>
                        <div class="h4 mb-0">{{ number_format($accounting->liquidPosition, 2) }}</div>
                        @foreach ($accounting->organizationBreakdown as $org)
                            <div class="small d-flex justify-content-between border-bottom py-1">
                                <span>{{ $org['organization_name'] ?? ('Org #' . ($org['organization_id'] ?? '?')) }}</span>
                                <span class="float-end">{{ number_format($org['liquid_position'] ?? 0, 2) }}</span>
                            </div>
                        @endforeach
                        @if ($accounting->organizationBreakdown === [])
                            <p class="text-muted small mt-2 mb-0">
                                No income statements available for the organizations in scope.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endisset

    @isset($findings)
        <div class="vicoba-card mb-3">
            <div class="card-header"><i class="bi bi-shield-exclamation me-1"></i> Anomaly findings</div>
            <div class="card-body">
                @if ($findings === [])
                    <p class="text-muted small mb-0">
                        No anomalies detected within scope during the current detection window.
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Finding</th>
                                    <th>Source</th>
                                    <th class="text-end">Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($findings as $finding)
                                    <tr>
                                        <td>
                                            <span class="badge bg-{{ $finding->severity === 'high' ? 'danger' : ($finding->severity === 'medium' ? 'warning' : 'info') }} text-dark">
                                                {{ $finding->severityLabel }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $finding->title }}</strong><br>
                                            <small class="text-muted">{{ $finding->description }}</small>
                                        </td>
                                        <td>
                                            <small>
                                                {{ $finding->sourceType }} #{{ $finding->sourceId }}
                                            </small>
                                        </td>
                                        <td class="text-end">{{ number_format($finding->amount ?? 0, 2) }}</td>
                                        <td>
                                            <span class="small">{{ $finding->statusLabel }}</span>
                                        </td>
                                        <td class="text-end">
                                            @if ($finding->status === 'detected' && $finding->id !== null)
                                                <form method="POST" action="{{ route('ai.intelligence.anomalies.review', $finding->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Mark reviewed</button>
                                                </form>
                                            @else
                                                <span class="text-muted small">Reviewed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endisset
@endsection