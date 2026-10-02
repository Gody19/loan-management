@extends('layouts.app')

@section('title', $action->title . ' - Management Action')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Management Action',
        'subtitle' => 'A human workflow record. This page never executes a financial or business operation.',
    ])
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="vicoba-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
                <i class="bi bi-clipboard-check me-1"></i> {{ $action->title }}
                <span class="badge bg-{{ $action->priority->color() }} text-dark">{{ $action->priority->label() }}</span>
                <span class="badge bg-{{ $action->status->color() }}">{{ $action->status->label() }}</span>
                <span class="badge bg-{{ $action->dueStateColor() }} text-dark">{{ $action->dueStateLabel() }}</span>
                <span class="badge bg-secondary text-dark">{{ $data->classificationLabel }}</span>
            </span>
            <a href="{{ route('ai.actions.index') }}" class="small">&larr; Action Center</a>
        </div>
        <div class="card-body">
            @if ($action->description)
                <p style="white-space: pre-wrap;">{{ $action->description }}</p>
            @else
                <p class="text-muted">No description.</p>
            @endif

            <div class="row g-3 small">
                <div class="col-md-3"><span class="text-muted">Assignee:</span> <strong>{{ $action->assignee->fullname ?? 'Unassigned' }}</strong></div>
                <div class="col-md-3"><span class="text-muted">Created by:</span> <strong>{{ $action->creator->fullname ?? '—' }}</strong></div>
                <div class="col-md-3"><span class="text-muted">Due date:</span> <strong>{{ $action->due_date?->toDateString() ?? 'No due date' }}</strong></div>
                <div class="col-md-3"><span class="text-muted">Scope:</span> <strong>{{ $action->branch->name ?? ($action->organization->name ?? 'Organization-wide') }}</strong></div>
            </div>

            @if ($action->completion_notes)
                <div class="alert alert-success mt-3 mb-0"><strong>Completion notes:</strong> {{ $action->completion_notes }}</div>
            @endif
            @if ($action->cancellation_reason)
                <div class="alert alert-secondary mt-3 mb-0"><strong>Cancellation reason:</strong> {{ $action->cancellation_reason }}</div>
            @endif
        </div>
    </div>

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-link-45deg me-1"></i> Source traceability</div>
        <div class="card-body">
            @if ($action->source_type === null)
                <p class="text-muted mb-0">This action was not raised from an intelligence artifact.</p>
            @else
                <div class="small">
                    <div><span class="text-muted">Source type:</span> <strong>{{ ucfirst(\App\Models\ManagementAction::sourceAliasFor($action->source_type) ?? 'record') }}</strong></div>
                    <div><span class="text-muted">Source record:</span> <strong>{{ $action->source_label ?? '#' . $action->source_id }}</strong> (id {{ $action->source_id }})</div>
                    @if ($sourceUrl)
                        <div class="mt-1"><a href="{{ $sourceUrl }}">Open source record <i class="bi bi-box-arrow-up-right"></i></a></div>
                    @endif
                    <div class="text-muted mt-2">The source keeps its own original classification; this action does not re-label it.</div>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="vicoba-card h-100">
                <div class="card-header"><i class="bi bi-pencil-square me-1"></i> Workflow</div>
                <div class="card-body">
                    @if ($action->isTerminal())
                        <p class="text-muted mb-0">This action is {{ mb_strtolower($action->status->label()) }} and is immutable. A correction is made by creating a new action.</p>
                    @else
                        @if ($can['manage'])
                            <form method="POST" action="{{ route('ai.actions.update', $action) }}" class="row g-2 mb-3">
                                @csrf
                                @method('PUT')
                                <div class="col-12">
                                    <label class="form-label small mb-1">Title</label>
                                    <input type="text" name="title" value="{{ $action->title }}" maxlength="160" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1">Description</label>
                                    <textarea name="description" rows="2" maxlength="4000" class="form-control form-control-sm">{{ $action->description }}</textarea>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Priority</label>
                                    <select name="priority" class="form-select form-select-sm">
                                        @foreach ($priorities as $priority)
                                            <option value="{{ $priority->value }}" @selected($action->priority === $priority)>{{ $priority->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Due date</label>
                                    <input type="date" name="due_date" value="{{ $action->due_date?->toDateString() }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save changes</button>
                                </div>
                            </form>
                        @endif

                        @if ($can['assign'])
                            <form method="POST" action="{{ route('ai.actions.assign', $action) }}" class="row g-2 mb-3">
                                @csrf
                                <div class="col-8">
                                    <label class="form-label small mb-1">Assignee</label>
                                    <select name="assigned_to" class="form-select form-select-sm">
                                        <option value="">Unassigned</option>
                                        @foreach ($assignees as $assignee)
                                            <option value="{{ $assignee->id }}" @selected((int) $action->assigned_to === (int) $assignee->id)>{{ $assignee->fullname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">Assign</button>
                                </div>
                            </form>
                        @endif

                        @if ($can['manage'])
                            @if ($action->status === \App\Enums\ManagementActionStatus::Open)
                                <form method="POST" action="{{ route('ai.actions.start', $action) }}" class="mb-3">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-play-fill me-1"></i>Start work</button>
                                </form>
                            @endif

                            <hr>

                            <form method="POST" action="{{ route('ai.actions.complete', $action) }}" class="row g-2 mb-2">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label small mb-1">Completion notes (optional)</label>
                                    <textarea name="completion_notes" rows="2" maxlength="2000" class="form-control form-control-sm"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Complete action</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('ai.actions.cancel', $action) }}" class="row g-2">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label small mb-1">Cancellation reason (optional)</label>
                                    <textarea name="cancellation_reason" rows="2" maxlength="2000" class="form-control form-control-sm"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel action</button>
                                </div>
                            </form>
                        @else
                            <p class="text-muted mb-0">You can view this action but not change it.</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="vicoba-card h-100">
                <div class="card-header"><i class="bi bi-clock-history me-1"></i> Timeline</div>
                <div class="card-body">
                    @forelse ($timeline as $event)
                        @php
                            $descriptions = [
                                'created' => 'created the action',
                                'updated' => 'edited the action',
                                'assigned' => 'changed the assignee',
                                'started' => 'started the action',
                                'completed' => 'completed the action',
                                'cancelled' => 'cancelled the action',
                            ];
                        @endphp
                        <div class="d-flex gap-2 {{ ! $loop->last ? 'mb-3' : '' }}">
                            <div class="text-muted"><i class="bi bi-dot"></i></div>
                            <div class="flex-grow-1">
                                <div class="small">
                                    <strong>{{ $event->actor->fullname ?? 'System' }}</strong>
                                    {{ $descriptions[$event->event] ?? $event->event }}
                                    @if ($event->old_status && $event->new_status && $event->old_status !== $event->new_status)
                                        <span class="text-muted">({{ $event->old_status }} &rarr; {{ $event->new_status }})</span>
                                    @endif
                                </div>
                                @if ($event->notes)
                                    <div class="small text-muted">{{ $event->notes }}</div>
                                @endif
                                <div class="small text-muted">{{ $event->created_at?->toDateTimeString() }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No history recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
