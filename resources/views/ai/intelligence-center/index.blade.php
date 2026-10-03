@extends('layouts.app')

@section('title', 'Management Intelligence Center - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Management Intelligence Center',
        'subtitle' => 'One executive workspace over your authorized portfolio, collections, cash flow, accounting, predictive outlooks, advisories, reports and schedules.',
    ])
@endsection

@section('content')
    {{--
        Management Intelligence Center (Phase 12.2).

        Read-only aggregation: every descriptive figure is computed by the
        existing Phase 11.7 intelligence services from authoritative FinancePro
        records within the trusted scope; predictions, insights, reports and
        schedules are read from the persisted Phase 11.8-12.1 artifacts. Nothing
        on this page changes a financial record, generates a report, runs a
        schedule or resolves an insight. Viewing the page changes no state.
    --}}

    @php
        $cap = $overview['capabilities'];
        $sections = $overview['sections'];
        $period = $overview['period'];
        $format = function ($value, $unit = '') {
            if ($value === null || $value === '') {
                return '—';
            }
            if (is_numeric($value)) {
                $formatted = number_format((float) $value, 2);
                return $unit === '%' ? $formatted . '%' : $formatted;
            }
            return (string) $value;
        };
        $narrativeQuery = array_merge(request()->query(), ['narrative' => 1]);
        $follow = $overview['management_follow_up'] ?? ['available' => false];
        $effectiveness = $follow['effectiveness'] ?? ['available' => false];
    @endphp

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-funnel me-1"></i> Scope &amp; period</div>
        <div class="card-body">
            <form method="GET" action="{{ route('ai.intelligence-center.index') }}" class="row g-2 align-items-end">
                @if ($branches->isNotEmpty())
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1">Branch</label>
                        <select name="branch_id" class="form-select form-select-sm">
                            <option value="">All authorized branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Period</label>
                    <select name="period" class="form-select form-select-sm">
                        @foreach ($overview['period_types'] as $type)
                            <option value="{{ $type->value }}" @selected(($filters['period'] ?? '') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Custom from</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Custom to</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>

                @if ($cap['insights'])
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Insight severity</label>
                        <select name="insight_severity" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (\App\Enums\ProactiveInsightSeverity::cases() as $severity)
                                <option value="{{ $severity->value }}" @selected(($filters['insight_severity'] ?? '') === $severity->value)>{{ $severity->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Insight status</label>
                        <select name="insight_status" class="form-select form-select-sm">
                            <option value="">Open</option>
                            @foreach (\App\Enums\ProactiveInsightStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(($filters['insight_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Insight type</label>
                        <select name="insight_type" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (\App\Enums\ProactiveInsightType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(($filters['insight_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($cap['reports'])
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Report type</label>
                        <select name="report_type" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (\App\Enums\ReportType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(($filters['report_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Report status</label>
                        <select name="report_status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (\App\Enums\ReportStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(($filters['report_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($cap['predictions'])
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Prediction domain</label>
                        <select name="prediction_type" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (\App\Enums\PredictiveInsightType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(($filters['prediction_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                    <a href="{{ route('ai.intelligence-center.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <span class="small text-muted ms-auto align-self-center">
                        Reporting period: <strong>{{ $period->label }}</strong>
                        ({{ $period->start }} → {{ $period->end }})
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="vicoba-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-award me-1"></i> Executive summary</span>
                    <span class="small text-muted">Every datum carries its classification: fact, trend, prediction or advisory.</span>
                </div>
                <div class="card-body">
                    @if ($overview['executive_summary'] === [])
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-shield-lock d-block mb-2" style="font-size: 2rem;"></i>
                            You do not have permission to view any management intelligence section.
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach ($overview['executive_summary'] as $item)
                                <div class="col-6 col-md-4 col-xl-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="text-muted small">{{ $item['label'] }}</div>
                                            <span class="badge bg-{{ $item['classification_color'] }} text-dark">{{ $item['classification_label'] }}</span>
                                        </div>
                                        <div class="h5 mb-1">{{ $format($item['value'], $item['unit']) }}</div>
                                        @if (! empty($item['source']))
                                            <div class="small text-muted">{{ $item['source'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($cap['actions'] && ($follow['available'] ?? false))
        <div class="vicoba-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-1"></i> Management follow-up <span class="badge bg-secondary text-dark">Workflow action</span></span>
                <a href="{{ route('ai.actions.index') }}" class="small">Open Action Center <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-3">
                    Human workflow records raised around the intelligence above. A management action is not a fact, trend,
                    prediction or advisory, and it never executes a financial or business decision.
                </div>
                <div class="row g-3 mb-3">
                    @php
                        $followCards = [
                            ['label' => 'Open', 'value' => $follow['open'], 'color' => 'secondary'],
                            ['label' => 'In progress', 'value' => $follow['in_progress'], 'color' => 'primary'],
                            ['label' => 'Overdue', 'value' => $follow['overdue'], 'color' => 'danger'],
                            ['label' => 'Due soon', 'value' => $follow['due_soon'], 'color' => 'warning'],
                            ['label' => 'Assigned to me', 'value' => $follow['assigned_to_me'], 'color' => 'info'],
                            ['label' => 'Unassigned', 'value' => $follow['unassigned'], 'color' => 'dark'],
                        ];
                    @endphp
                    @foreach ($followCards as $card)
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">{{ $card['label'] }}</div>
                                <div class="h5 mb-0 text-{{ $card['color'] }}">{{ number_format($card['value']) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (($effectiveness['available'] ?? false))
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <span class="fw-semibold small">Action effectiveness</span>
                                <span class="badge bg-secondary text-dark ms-1">Workflow measurement</span>
                            </div>
                            <a href="{{ route('ai.actions.effectiveness') }}" class="small">Open effectiveness dashboard <i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                        @php
                            $measure = function ($value, $unit) {
                                if ($value === null) {
                                    return 'N/A';
                                }
                                $formatted = number_format((float) $value, $unit === '%' ? 1 : 1);

                                return $unit === '%' ? $formatted . '%' : $formatted . ' ' . $unit;
                            };
                            $effectivenessCards = [
                                ['label' => 'Total actions', 'value' => number_format($effectiveness['total_actions'])],
                                ['label' => 'Unresolved', 'value' => number_format($effectiveness['unresolved_actions'])],
                                ['label' => 'Completion rate', 'value' => $measure($effectiveness['completion_rate'], '%')],
                                ['label' => 'Cancellation rate', 'value' => $measure($effectiveness['cancellation_rate'], '%')],
                                ['label' => 'Average completion time', 'value' => $measure($effectiveness['average_completion_days'], 'days')],
                                ['label' => 'Average time to start', 'value' => $measure($effectiveness['average_time_to_start_days'], 'days')],
                            ];
                        @endphp
                        <div class="row g-2">
                            @foreach ($effectivenessCards as $card)
                                <div class="col-6 col-md-4 col-xl-2">
                                    <div class="text-muted small">{{ $card['label'] }}</div>
                                    <div class="h6 mb-0">{{ $card['value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="small text-muted mt-2">
                            Measured over every action in your authorized scope. A figure is shown as N/A when it cannot be derived
                            from the records; it is never shown as zero. These measurements never evaluate a person and never
                            escalate, reassign or close an action.
                        </div>
                    </div>
                @endif

                <div class="fw-semibold small mb-2">Recently completed</div>
                @if (($follow['recently_completed'] ?? collect())->isEmpty())
                    <p class="text-muted small mb-0">No completed actions yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Action</th><th>Priority</th><th>Completed by</th><th>Completed</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($follow['recently_completed'] as $completed)
                                    <tr>
                                        <td>{{ $completed->title }}</td>
                                        <td><span class="badge bg-{{ $completed->priority->color() }} text-dark">{{ $completed->priority->label() }}</span></td>
                                        <td class="small">{{ $completed->assignee->fullname ?? '—' }}</td>
                                        <td class="small text-muted">{{ $completed->completed_at?->toDateTimeString() ?? '—' }}</td>
                                        <td class="text-end"><a href="{{ route('ai.actions.show', $completed) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @foreach ($sections as $section)
        <div class="vicoba-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi {{ $section['icon'] }} me-1"></i> {{ $section['title'] }}</span>
                @if (in_array($section['key'], ['portfolio', 'risk'], true))
                    <a href="{{ route('ai.intelligence.index') }}" class="small">Open financial intelligence <i class="bi bi-box-arrow-up-right"></i></a>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    @foreach ($section['items'] as $item)
                        <div class="col-6 col-md-4 col-xl-3">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="text-muted small">{{ $item['label'] }}</div>
                                    <span class="badge bg-{{ $item['classification_color'] }} text-dark">{{ $item['classification_label'] }}</span>
                                </div>
                                <div class="h5 mb-0">{{ $format($item['value'], $item['unit']) }}</div>
                                @if (! empty($item['note']))
                                    <div class="small text-muted mt-1">{{ $item['note'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($section['key'] === 'portfolio' && ! empty($section['data']['portfolio']->compositionByPlan))
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead><tr><th>Loan plan</th><th class="text-end">Active</th><th class="text-end">Principal outstanding</th></tr></thead>
                            <tbody>
                                @foreach ($section['data']['portfolio']->compositionByPlan as $row)
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

                @if ($section['key'] === 'risk')
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead><tr><th>Aging bucket</th><th class="text-end">Loans</th><th class="text-end">Principal</th><th class="text-end">Percent</th></tr></thead>
                            <tbody>
                                @foreach ($section['data']['par']->buckets as $bucket)
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
                @endif

                @if ($section['key'] === 'cash_flow' && ! empty($section['data']['trends']->trend))
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead><tr><th>Month <span class="badge bg-info text-dark">Trend</span></th><th class="text-end">Disbursed</th><th class="text-end">Collected</th><th class="text-end">Net</th></tr></thead>
                            <tbody>
                                @foreach ($section['data']['trends']->trend as $month)
                                    <tr>
                                        <td>{{ $month['label'] ?? $month['period'] ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($month['total_disbursed'] ?? 0, 2) }}</td>
                                        <td class="text-end">{{ number_format($month['total_collected'] ?? 0, 2) }}</td>
                                        <td class="text-end">{{ number_format($month['net_cash_flow'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($section['key'] === 'accounting')
                    @if (! empty($section['data']['branch_scoped']))
                        <div class="small text-muted mb-2">Accounting intelligence is organization-wide; the branch filter does not narrow it.</div>
                    @endif
                    @if (! empty($section['data']['accounting']->organizationBreakdown))
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead><tr><th>Organization</th><th class="text-end">Income</th><th class="text-end">Expenses</th><th class="text-end">Net</th><th class="text-end">Liquid</th></tr></thead>
                                <tbody>
                                    @foreach ($section['data']['accounting']->organizationBreakdown as $org)
                                        <tr>
                                            <td>{{ $org['organization_name'] ?? ('Org #' . ($org['organization_id'] ?? '?')) }}</td>
                                            <td class="text-end">{{ number_format($org['total_income'] ?? 0, 2) }}</td>
                                            <td class="text-end">{{ number_format($org['total_expenses'] ?? 0, 2) }}</td>
                                            <td class="text-end">{{ number_format($org['net_income'] ?? 0, 2) }}</td>
                                            <td class="text-end">{{ number_format($org['liquid_position'] ?? 0, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                @if ($section['key'] === 'operations' && ! empty($section['data']['findings']) && $section['data']['findings']->isNotEmpty())
                    <div class="small text-muted mb-1">Latest detection pass: {{ $section['data']['findings_date'] ?? '—' }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead><tr><th>Severity <span class="badge bg-primary text-dark">Fact</span></th><th>Finding</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                @foreach ($section['data']['findings'] as $finding)
                                    <tr>
                                        <td><span class="badge bg-{{ $finding->severity->value === 'high' ? 'danger' : ($finding->severity->value === 'medium' ? 'warning' : 'info') }} text-dark">{{ $finding->severity->label() }}</span></td>
                                        <td>{{ $finding->title ?? ($finding->type->label() ?? 'Finding') }}</td>
                                        <td class="text-end">{{ number_format((float) ($finding->amount ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if ($cap['insights'])
        <div class="vicoba-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bell me-1"></i> Attention queue <span class="badge bg-secondary text-dark">Advisory</span></span>
                <span class="small text-muted">Rule-based advisories from Phase 11.9. Viewing does not change an insight's status.</span>
            </div>
            <div class="card-body p-0">
                @forelse ($overview['attention_queue'] as $insight)
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 p-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold small">{{ $insight->title }}</span>
                                <span class="badge bg-{{ $insight->severity->color() }} text-dark">{{ $insight->severity->label() }}</span>
                                <span class="badge bg-light text-dark border">{{ $insight->type->label() }}</span>
                                <span class="badge bg-{{ $insight->status->color() }}">{{ $insight->status->label() }}</span>
                            </div>
                            <div class="small text-muted mt-1">{{ $insight->summary }}</div>
                            @if ($insight->recommendation)
                                <div class="small mt-1"><i class="bi bi-lightbulb me-1"></i><strong>Suggested action:</strong> {{ $insight->recommendation }}</div>
                            @endif
                            <div class="small text-muted mt-1">
                                <i class="bi bi-clock me-1"></i>Detected {{ $insight->generated_at?->diffForHumans() }}
                                @if ($insight->branch)
                                    &middot; {{ $insight->branch->name }}
                                @endif
                            </div>
                        </div>
                        @if ($insight->status->isOpen())
                            <div class="d-flex gap-1 flex-shrink-0">
                                <form action="{{ route('ai.intelligence.insights.acknowledge', $insight) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Acknowledge</button>
                                </form>
                                <form action="{{ route('ai.intelligence.insights.resolve', $insight) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Resolve</button>
                                </form>
                                <form action="{{ route('ai.intelligence.insights.dismiss', $insight) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-shield-check d-block mb-2" style="font-size: 2rem;"></i>
                        No insights match the current filter.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    @if ($cap['predictions'])
        <div class="vicoba-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up me-1"></i> Predictive outlook <span class="badge bg-warning text-dark">Prediction</span></span>
                <span class="small text-muted">Prediction — not an actual recorded financial result.</span>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-3">
                    Persisted Phase 11.8 statistical snapshots, shown exactly as generated (including stale, poor-quality or
                    superseded states). They are organization-scoped indications computed from historical records — never
                    guarantees, never branch-specific, and never inputs to a loan decision, rate or accounting rule.
                </div>
                @foreach ($overview['predictions'] as $domain)
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
                                        <th>Scope</th>
                                        <th>Data through</th>
                                        <th class="text-end">Outlook value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($domain['rows'] as $row)
                                        @php $prediction = $row['prediction']; @endphp
                                        @if ($prediction === null)
                                            <tr><td colspan="7" class="text-muted small">{{ $row['organization_name'] }}: no persisted prediction for this domain.</td></tr>
                                        @else
                                            <tr>
                                                <td>{{ $row['organization_name'] }}</td>
                                                <td><span class="badge bg-{{ $prediction->status->color() }}">{{ $prediction->status->label() }}</span></td>
                                                <td><span class="badge bg-{{ $prediction->data_quality->color() }}">{{ $prediction->data_quality->label() }}</span></td>
                                                <td><span class="badge bg-{{ $prediction->confidence->color() }}">{{ $prediction->confidence->label() }}</span></td>
                                                <td class="small text-muted">{{ $row['scope_label'] }}</td>
                                                <td>{{ $prediction->data_through?->toDateString() ?? '—' }}</td>
                                                <td class="text-end">{{ $prediction->value_total === null ? '—' : number_format((float) $prediction->value_total, 2) }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
                @if ($overview['predictions'] === [])
                    <p class="text-muted small mb-0">No predictive domains are available for the current filter.</p>
                @endif
            </div>
        </div>
    @endif

    @if ($cap['reports'])
        <div class="vicoba-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-1"></i> Report library</span>
                <a href="{{ route('ai.reports.index') }}" class="small">Open reporting <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
            <div class="card-body p-0">
                @if ($overview['reports']->isEmpty())
                    <div class="p-4 text-center text-muted">No reports match the current filter.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr><th>Type</th><th>Period</th><th>Branch</th><th>Status</th><th>Generated</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($overview['reports'] as $report)
                                    <tr>
                                        <td>{{ $report->report_type->label() }}</td>
                                        <td class="small">{{ $report->period_start?->toDateString() }} → {{ $report->period_end?->toDateString() }}</td>
                                        <td>{{ $report->branch?->name ?? 'All branches' }}</td>
                                        <td><span class="badge bg-{{ $report->status->color() }}">{{ $report->status->label() }}</span></td>
                                        <td class="small text-muted">{{ $report->generated_at?->toDateTimeString() ?? '—' }}</td>
                                        <td class="text-end">
                                            @if ($report->status->isReportable())
                                                <a href="{{ route('ai.reports.show', $report) }}" class="btn btn-sm btn-outline-primary">View</a>
                                            @else
                                                <span class="text-muted small">—</span>
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
    @endif

    @if ($cap['schedules'])
        <div class="vicoba-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar2-week me-1"></i> Scheduled reports</span>
                <a href="{{ route('ai.reports.index') }}" class="small">Manage schedules <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
            <div class="card-body">
                @if ($overview['schedules']->isEmpty())
                    <p class="text-muted small mb-0">No report schedules in your authorized scope.</p>
                @else
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead><tr><th>Name</th><th>Type</th><th>Frequency</th><th>Next run</th><th>Last run</th><th>State</th></tr></thead>
                            <tbody>
                                @foreach ($overview['schedules'] as $schedule)
                                    <tr>
                                        <td>{{ $schedule->name }}</td>
                                        <td class="small">{{ $schedule->report_type->label() }}</td>
                                        <td class="small">{{ $schedule->frequency->label() }}</td>
                                        <td class="small">{{ $schedule->next_run_at?->toDateTimeString() ?? '—' }}</td>
                                        <td class="small">{{ $schedule->last_run_at?->toDateTimeString() ?? '—' }}</td>
                                        <td><span class="badge bg-{{ $schedule->is_active ? 'success' : 'secondary' }}">{{ $schedule->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="fw-semibold small mb-2">Recent runs</div>
                    @if ($overview['recent_runs']->isEmpty())
                        <p class="text-muted small mb-0">No runs recorded yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead><tr><th>Schedule</th><th>Period</th><th>Status</th><th class="text-end">Notified</th><th>Started</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($overview['recent_runs'] as $run)
                                        <tr>
                                            <td>{{ $run->schedule?->name ?? '—' }}</td>
                                            <td class="small">{{ $run->period_start?->toDateString() }} → {{ $run->period_end?->toDateString() }}</td>
                                            <td><span class="badge bg-{{ $run->status->color() }}">{{ $run->status->label() }}</span></td>
                                            <td class="text-end">{{ number_format($run->notifications_sent) }}</td>
                                            <td class="small text-muted">{{ $run->started_at?->toDateTimeString() ?? '—' }}</td>
                                            <td class="text-end">
                                                @if ($run->report && $run->report->status->isReportable())
                                                    <a href="{{ route('ai.reports.show', $run->report) }}" class="btn btn-sm btn-outline-primary">View report</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <div class="vicoba-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-robot me-1"></i> AI explanation <span class="badge bg-secondary text-dark">AI-generated advisory explanation</span></span>
            <a href="{{ route('ai.intelligence-center.index', $narrativeQuery) }}" class="small">Generate AI explanation <i class="bi bi-arrow-repeat"></i></a>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-2">
                Explanatory only. The AI receives a sanitized, classified summary of the deterministic output and cannot
                compute, query, change or override any figure, prediction, insight, report or business record.
            </div>
            @php $narrative = $overview['narrative']; @endphp
            @if (is_array($narrative) && ($narrative['available'] ?? false) === true)
                <div class="border rounded p-3 bg-light">
                    <div class="small text-muted mb-2">Provider: {{ $narrative['provider'] ?? 'unknown' }} / {{ $narrative['model'] ?? 'unknown' }}</div>
                    <div style="white-space: pre-wrap;">{{ $narrative['summary'] }}</div>
                </div>
            @elseif (is_array($narrative))
                <div class="alert alert-warning mb-0">
                    AI explanation unavailable: {{ $narrative['reason'] ?? 'no explanation was generated.' }}
                    The management intelligence above is unaffected.
                </div>
            @else
                <p class="text-muted small mb-0">No AI explanation generated for this view. Use the link above to request one.</p>
            @endif
        </div>
    </div>
@endsection
