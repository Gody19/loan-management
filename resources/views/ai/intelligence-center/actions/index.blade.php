@extends('layouts.app')

@section('title', 'Management Actions - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Management Actions',
        'subtitle' => 'A human workflow record of follow-ups raised around your advisory intelligence. Actions never execute a financial or business decision.',
    ])
@endsection

@section('content')
    {{--
        Action Center (Phase 12.3).

        This is a workflow surface: it lists, filters and links to management
        actions. Displaying the page changes no state and never touches the
        intelligence it references.
    --}}

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        @php
            $cards = [
                ['label' => 'Open', 'value' => $counts['open'], 'icon' => 'bi-inbox', 'color' => 'secondary'],
                ['label' => 'In progress', 'value' => $counts['in_progress'], 'icon' => 'bi-hourglass-split', 'color' => 'primary'],
                ['label' => 'Overdue', 'value' => $counts['overdue'], 'icon' => 'bi-exclamation-triangle', 'color' => 'danger'],
                ['label' => 'Due soon', 'value' => $counts['due_soon'], 'icon' => 'bi-alarm', 'color' => 'warning'],
                ['label' => 'Assigned to me', 'value' => $counts['assigned_to_me'], 'icon' => 'bi-person-check', 'color' => 'info'],
                ['label' => 'Unassigned', 'value' => $counts['unassigned'], 'icon' => 'bi-person-dash', 'color' => 'dark'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="vicoba-card h-100">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">{{ $card['label'] }}</span>
                            <i class="bi {{ $card['icon'] }} text-{{ $card['color'] }}"></i>
                        </div>
                        <div class="h4 mb-0">{{ number_format($card['value']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-funnel me-1"></i> Filters</span>
            @if ($can['manage'])
                <a href="{{ route('ai.actions.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>New action</a>
            @endif
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('ai.actions.index') }}" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Open &amp; in progress</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Priority</label>
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Due</label>
                    <select name="due" class="form-select form-select-sm">
                        <option value="">Any</option>
                        <option value="open" @selected(($filters['due'] ?? '') === 'open')>Open / in progress</option>
                        <option value="overdue" @selected(($filters['due'] ?? '') === 'overdue')>Overdue</option>
                        <option value="due_soon" @selected(($filters['due'] ?? '') === 'due_soon')>Due soon</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Assignee</label>
                    <select name="assigned_to" class="form-select form-select-sm">
                        <option value="">All</option>
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
                        <option value="">All</option>
                        @foreach ($sourceTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['source_type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($branches->isNotEmpty())
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Branch</label>
                        <select name="branch_id" class="form-select form-select-sm">
                            <option value="">All my branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                    <a href="{{ route('ai.actions.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-1"></i> Actions</span>
            <span class="badge bg-secondary text-dark">Workflow action &mdash; not a fact, trend, prediction or advisory</span>
        </div>
        <div class="card-body p-0">
            @if ($actions->isEmpty())
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-clipboard-x d-block mb-2" style="font-size: 2rem;"></i>
                    No management actions match the current filter.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assignee</th>
                                <th>Due</th>
                                <th>Source</th>
                                <th>Created by</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actions as $action)
                                <tr>
                                    <td>
                                        <a href="{{ route('ai.actions.show', $action) }}" class="fw-semibold">{{ $action->title }}</a>
                                        @if ($action->branch)
                                            <div class="small text-muted">{{ $action->branch->name }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-{{ $action->priority->color() }} text-dark">{{ $action->priority->label() }}</span></td>
                                    <td><span class="badge bg-{{ $action->status->color() }}">{{ $action->status->label() }}</span></td>
                                    <td class="small">{{ $action->assignee->fullname ?? '—' }}</td>
                                    <td class="small">
                                        <div>{{ $action->due_date?->toDateString() ?? '—' }}</div>
                                        <span class="badge bg-{{ $action->dueStateColor() }} text-dark">{{ $action->dueStateLabel() }}</span>
                                    </td>
                                    <td class="small text-muted">{{ $action->source_label ?? '—' }}</td>
                                    <td class="small text-muted">{{ $action->creator->fullname ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('ai.actions.show', $action) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($actions->hasPages())
            <div class="card-footer">
                {{ $actions->links() }}
            </div>
        @endif
    </div>
@endsection
