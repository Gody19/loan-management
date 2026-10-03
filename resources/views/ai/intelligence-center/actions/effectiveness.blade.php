@extends('layouts.app')

@section('title', 'Action Effectiveness - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Action Effectiveness',
        'subtitle' => 'How management follow-up actions are being handled, completed, delayed and reviewed.',
    ])
@endsection

@section('content')
    {{--
        Management Action Effectiveness & Executive Accountability (Phase 12.4).

        Strictly read-only measurement of existing Phase 12.3 management actions.
        Every figure on this page is a deterministic aggregate over authoritative
        action records inside the acting user's authorized organization and branch
        scope. Loading the page never creates, assigns, starts, escalates,
        completes or cancels an action, never runs a schedule or generates a
        report, prediction or insight, and never evaluates or scores any person.
    --}}

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $metrics = $report['cohort']['metrics'];
        $live = $report['live']['metrics'];
        $na = 'N/A';
        $count = fn ($value) => $value === null ? $na : number_format((float) $value);
        $percent = fn ($value) => $value === null ? $na : number_format((float) $value, 1) . '%';
        $days = fn ($value) => $value === null ? $na : number_format((float) $value, 1) . ' days';
        $directionColor = fn ($direction) => match ($direction) {
            'up' => 'info',
            'down' => 'secondary',
            'flat' => 'dark',
            default => 'light',
        };
        $directionLabel = fn ($direction) => match ($direction) {
            'up' => 'Up',
            'down' => 'Down',
            'flat' => 'No change',
            default => 'Unavailable',
        };
        $agingTotal = max(1, (int) $report['aging']['unresolved']);
        $comparisonRows = [
            'total' => 'Actions raised',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'completion_rate' => 'Completion rate',
            'cancellation_rate' => 'Cancellation rate',
        ];
    @endphp

    <div class="vicoba-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-funnel me-1"></i> Scope &amp; period</span>
            <a href="{{ route('ai.actions.index') }}" class="small">Action Center <i class="bi bi-box-arrow-up-right"></i></a>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('ai.actions.effectiveness') }}" class="row g-2 align-items-end">
                @if ($branches->isNotEmpty())
                    <div class="col-6 col-md-3">
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
                        @foreach ($periodTypes as $type)
                            <option value="{{ $type->value }}" @selected($period->type === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Custom from</label>
                    <input type="date" name="from" value="{{ $displayFilters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Custom to</label>
                    <input type="date" name="to" value="{{ $displayFilters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Assignee</label>
                    <select name="assigned_to" class="form-select form-select-sm">
                        <option value="">Everyone</option>
                        <option value="me" @selected(($filters['assigned_to'] ?? '') === 'me')>Assigned to me</option>
                        <option value="unassigned" @selected(($filters['assigned_to'] ?? '') === 'unassigned')>Unassigned</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $assignee->id)>{{ $assignee->fullname }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Source</label>
                    <select name="source_type" class="form-select form-select-sm">
                        <option value="">All sources</option>
                        @foreach ($sourceOptions as $source)
                            <option value="{{ $source }}" @selected(($filters['source_type'] ?? '') === $source)>{{ ucfirst(str_replace('_', ' ', $source)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Record status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All records</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($displayFilters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
                </div>
            </form>
            <div class="small text-muted mt-2">
                The record status filter narrows the detailed record list at the bottom of this page. The metrics above are
                always measured over every action raised in the selected period.
            </div>
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header">
            <i class="bi bi-clipboard-data me-1"></i> {{ $report['cohort']['label'] }}
            <span class="badge bg-primary text-dark">Observed fact</span>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-3">
                Every figure below is a count or a deterministic average of management action workflow records. Nothing here
                is a prediction, an advisory or an evaluation of any person.
            </div>

            <div class="row g-3 mb-3">
                @php
                    $cards = [
                        ['label' => 'Actions raised', 'value' => $count($metrics['total']), 'color' => 'primary'],
                        ['label' => 'Open', 'value' => $count($metrics['open']), 'color' => 'secondary'],
                        ['label' => 'In progress', 'value' => $count($metrics['in_progress']), 'color' => 'primary'],
                        ['label' => 'Completed', 'value' => $count($metrics['completed']), 'color' => 'success'],
                        ['label' => 'Cancelled', 'value' => $count($metrics['cancelled']), 'color' => 'dark'],
                        ['label' => 'Overdue', 'value' => $count($metrics['overdue']), 'color' => 'danger'],
                        ['label' => 'Due soon', 'value' => $count($metrics['due_soon']), 'color' => 'warning'],
                        ['label' => 'Unassigned', 'value' => $count($metrics['unassigned']), 'color' => 'dark'],
                    ];
                @endphp
                @foreach ($cards as $card)
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <div class="h5 mb-0 text-{{ $card['color'] }}">{{ $card['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3">
                @php
                    $measurements = [
                        ['label' => 'Completion rate', 'value' => $percent($metrics['completion_rate']), 'key' => 'completion_rate', 'color' => 'success'],
                        ['label' => 'Cancellation rate', 'value' => $percent($metrics['cancellation_rate']), 'key' => 'cancellation_rate', 'color' => 'dark'],
                        ['label' => 'Average completion time', 'value' => $days($metrics['average_completion_days']), 'key' => null, 'color' => 'primary'],
                        ['label' => 'Average time to start', 'value' => $days($metrics['average_time_to_start_days']), 'key' => null, 'color' => 'primary'],
                    ];
                @endphp
                @foreach ($measurements as $measurement)
                    @php
                        $change = $measurement['key'] !== null ? $report['comparison'][$measurement['key']] : null;
                    @endphp
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ $measurement['label'] }}</div>
                            <div class="h5 mb-0 text-{{ $measurement['color'] }}">{{ $measurement['value'] }}</div>
                            @if ($change !== null)
                                @if ($change['available'])
                                    <div class="small text-muted mt-1">
                                        {{ $directionLabel($change['direction']) }}
                                        @if ($change['change'] !== null)
                                            by {{ number_format(abs((float) $change['change']), 1) }}
                                            @if ($measurement['key'] === 'completion_rate' || $measurement['key'] === 'cancellation_rate')
                                                pp
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <div class="small text-muted mt-1" title="{{ $change['unavailable_reason'] }}">No comparison available</div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="small text-muted mt-3">
                {{ $na }} means the figure cannot be derived from the available records — a rate with no eligible actions, or an
                average with no completed or started action. It is never shown as zero.
            </div>
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> Period comparison <span class="badge bg-info text-dark">Trend</span></div>
        <div class="card-body">
            @if (! $report['has_comparison'])
                <p class="text-muted mb-0">No previous period is available for this selection, so no change is shown.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Measure</th>
                                <th class="text-end">{{ $report['cohort']['label'] }}</th>
                                <th class="text-end">Previous period</th>
                                <th class="text-end">Change</th>
                                <th class="text-end">Percentage</th>
                                <th>Direction</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comparisonRows as $key => $label)
                                @php
                                    $comparison = $report['comparison'][$key];
                                    $isRate = in_array($key, ['completion_rate', 'cancellation_rate'], true);
                                    $format = $isRate ? $percent : $count;
                                @endphp
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-end">{{ $format($comparison['current']) }}</td>
                                    @if ($comparison['available'])
                                        <td class="text-end">{{ $format($comparison['previous']) }}</td>
                                        <td class="text-end">{{ $comparison['change'] === null ? $na : number_format(abs((float) $comparison['change']), 1) . ($isRate ? ' pp' : '') }}</td>
                                        <td class="text-end">{{ $comparison['percent_change'] === null ? $na : number_format((float) $comparison['percent_change'], 1) . '%' }}</td>
                                        <td><span class="badge bg-{{ $directionColor($comparison['direction']) }} text-dark">{{ $directionLabel($comparison['direction']) }}</span></td>
                                    @else
                                        <td colspan="4" class="text-muted small">{{ $comparison['unavailable_reason'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-hourglass me-1"></i> Action aging <span class="badge bg-secondary text-dark">Unresolved only</span></div>
        <div class="card-body">
            @if ($report['aging']['unresolved'] === 0)
                <p class="text-muted mb-0">No unresolved actions were raised in this period.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Age since raised</th><th class="text-end">Actions</th><th style="width: 45%">Share</th></tr></thead>
                        <tbody>
                            @foreach ($report['aging']['buckets'] as $bucket)
                                @php $share = $bucket['count'] / $agingTotal * 100; @endphp
                                <tr>
                                    <td>{{ $bucket['label'] }}</td>
                                    <td class="text-end">{{ number_format($bucket['count']) }}</td>
                                    <td>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-secondary" style="width: {{ number_format($share, 1) }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($report['aging']['unclassified'] > 0)
                    <div class="alert alert-warning py-1 small mb-2">
                        {{ number_format($report['aging']['unclassified']) }} unresolved action(s) have no usable creation time
                        and are excluded from the buckets above rather than being counted as 31+ days old.
                    </div>
                @endif
                <div class="small text-muted mt-2">Aging is measured from the recorded creation time. An action is never changed, closed or escalated because of its age.</div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="vicoba-card h-100">
                <div class="card-header"><i class="bi bi-diagram-2 me-1"></i> Branch distribution</div>
                <div class="card-body">
                    @if ($report['by_branch']['rows'] === [])
                        <p class="text-muted mb-0">No actions were raised in this period.</p>
                    @else
                        @if ($report['by_branch']['truncated'])
                            <div class="alert alert-warning py-1 small">Only the first {{ $report['by_branch']['limit'] }} rows are shown.</div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Scope</th><th class="text-end">Total</th><th class="text-end">Open</th><th class="text-end">In progress</th><th class="text-end">Completed</th><th class="text-end">Overdue</th><th class="text-end">Cancelled</th><th class="text-end">Unassigned</th></tr></thead>
                                <tbody>
                                    @foreach ($report['by_branch']['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td class="text-end">{{ number_format($row['total']) }}</td>
                                            <td class="text-end">{{ number_format($row['open']) }}</td>
                                            <td class="text-end">{{ number_format($row['in_progress']) }}</td>
                                            <td class="text-end">{{ number_format($row['completed']) }}</td>
                                            <td class="text-end">{{ number_format($row['overdue']) }}</td>
                                            <td class="text-end">{{ number_format($row['cancelled']) }}</td>
                                            <td class="text-end">{{ number_format($row['unassigned']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">Workload counts only. No branch is ranked, scored or compared against another.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="vicoba-card h-100">
                <div class="card-header"><i class="bi bi-people me-1"></i> Assignment distribution</div>
                <div class="card-body">
                    @if ($report['by_assignee']['rows'] === [])
                        <p class="text-muted mb-0">No actions were raised in this period.</p>
                    @else
                        @if ($report['by_assignee']['truncated'])
                            <div class="alert alert-warning py-1 small">Only the first {{ $report['by_assignee']['limit'] }} rows are shown.</div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Assignee</th><th class="text-end">Assigned</th><th class="text-end">Open</th><th class="text-end">In progress</th><th class="text-end">Completed</th><th class="text-end">Overdue</th><th class="text-end">Cancelled</th></tr></thead>
                                <tbody>
                                    @foreach ($report['by_assignee']['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td class="text-end">{{ number_format($row['total']) }}</td>
                                            <td class="text-end">{{ number_format($row['open']) }}</td>
                                            <td class="text-end">{{ number_format($row['in_progress']) }}</td>
                                            <td class="text-end">{{ number_format($row['completed']) }}</td>
                                            <td class="text-end">{{ number_format($row['overdue']) }}</td>
                                            <td class="text-end">{{ number_format($row['cancelled']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">
                            Neutral workload facts. This page does not score, rate or rank any employee or manager.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-signpost-split me-1"></i> Source distribution</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Source</th><th class="text-end">Actions</th><th style="width: 45%">Share</th></tr></thead>
                    <tbody>
                        @php $sourceTotal = max(1, (int) collect($report['by_source'])->sum('count')); @endphp
                        @foreach ($report['by_source'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-end">{{ number_format($row['count']) }}</td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: {{ number_format($row['count'] / $sourceTotal * 100, 1) }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-2">Descriptive provenance of where follow-ups were raised. No source is treated as more important than another.</div>
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header">{{ $report['live']['label'] }}</div>
        <div class="card-body">
            <div class="row g-3">
                @php
                    $liveCards = [
                        ['label' => 'All actions in scope', 'value' => $count($live['total'])],
                        ['label' => 'Unresolved', 'value' => $count($live['unresolved'])],
                        ['label' => 'Overdue now', 'value' => $count($live['overdue'])],
                        ['label' => 'Due soon', 'value' => $count($live['due_soon'])],
                        ['label' => 'Unassigned', 'value' => $count($live['unassigned'])],
                        ['label' => 'Without a due date', 'value' => $count($live['without_due_date'])],
                        ['label' => 'Completion rate', 'value' => $percent($live['completion_rate'])],
                        ['label' => 'Average completion time', 'value' => $days($live['average_completion_days'])],
                    ];
                @endphp
                @foreach ($liveCards as $card)
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <div class="h6 mb-0">{{ $card['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($live['without_due_date'] > 0)
                <div class="small text-muted mt-3">
                    {{ number_format($live['without_due_date']) }} action(s) have no due date, so they can never be reported as overdue or due soon.
                </div>
            @endif
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-check2-circle me-1"></i> Recently completed</div>
        <div class="card-body">
            @if ($report['live']['recently_completed']->isEmpty())
                <p class="text-muted mb-0">No completed actions yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Action</th><th>Scope</th><th>Completed by</th><th>Completed</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($report['live']['recently_completed'] as $completed)
                                <tr>
                                    <td>{{ $completed->title }}</td>
                                    <td class="small">{{ $completed->branch->name ?? 'Organization-wide' }}</td>
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

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-list-check me-1"></i> Action records in scope</div>
        <div class="card-body">
            @if ($actions->isEmpty())
                <p class="text-muted mb-0">No management actions match these filters.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Action</th><th>Scope</th><th>Status</th><th>Priority</th><th>Assignee</th><th>Due</th><th>Raised</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($actions as $action)
                                <tr>
                                    <td>{{ $action->title }}</td>
                                    <td class="small">{{ $action->branch->name ?? 'Organization-wide' }}</td>
                                    <td><span class="badge bg-{{ $action->status->color() }}">{{ $action->status->label() }}</span></td>
                                    <td><span class="badge bg-{{ $action->priority->color() }} text-dark">{{ $action->priority->label() }}</span></td>
                                    <td class="small">{{ $action->assignee->fullname ?? 'Unassigned' }}</td>
                                    <td class="small"><span class="badge bg-{{ $action->dueStateColor() }} text-dark">{{ $action->dueStateLabel() }}</span></td>
                                    <td class="small text-muted">{{ $action->created_at?->toDateTimeString() }}</td>
                                    <td class="text-end"><a href="{{ route('ai.actions.show', $action) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $actions->links() }}
            @endif
        </div>
    </div>
@endsection
