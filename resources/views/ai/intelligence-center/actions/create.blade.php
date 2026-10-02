@extends('layouts.app')

@section('title', 'New Management Action - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'New Management Action',
        'subtitle' => 'Record a human follow-up. Assigning a priority or due date is a human decision; the AI never sets one for you.',
    ])
@endsection

@section('content')
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="vicoba-card mb-3">
        <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Action details</div>
        <div class="card-body">
            <form method="POST" action="{{ route('ai.actions.store') }}" class="row g-3">
                @csrf

                <div class="col-12">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="160" class="form-control" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="4" maxlength="4000" class="form-control">{{ old('description') }}</textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Priority is chosen by you; it is never derived from an AI insight severity.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Due date</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-select">
                        <option value="">Organization-wide</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @if ($branches->isEmpty())
                        <div class="form-text">You have organization-wide scope; leave blank for a whole-organization action.</div>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assignee</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">Unassigned</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected((string) old('assigned_to') === (string) $assignee->id)>{{ $assignee->fullname }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">You may always assign to yourself. Assigning to somebody else requires the assign capability.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Source type</label>
                    <select name="source_type" class="form-select">
                        <option value="">None</option>
                        @foreach ($sourceTypes as $type)
                            <option value="{{ $type }}" @selected(old('source_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Source record id</label>
                    <input type="number" name="source_id" value="{{ old('source_id') }}" class="form-control" min="1">
                    <div class="form-text">Optional. The record must belong to your organization.</div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create action</button>
                    <a href="{{ route('ai.actions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
