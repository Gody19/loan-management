@extends('layouts.app')

@section('title', 'VICOBA Groups - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'VICOBA Groups',
        'subtitle' => 'Manage VICOBA groups',
        'breadcrumb' => [
            ['label' => 'VICOBA Groups'],
        ],
        'actions' => '<a href="' . route('vicoba-groups.create') . '" class="btn btn-primary vicoba-btn"><i class="bi bi-plus-lg me-1"></i> Create Group</a>',
    ])
@endsection

@section('content')

<div class="card vicoba-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('vicoba-groups.index') }}" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search by name, code..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="branch_id">
                    <option value="">All Branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }} ({{ $branch->organization->name }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\GroupStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100 vicoba-btn">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('vicoba-groups.index') }}" class="btn btn-outline-secondary w-100 vicoba-btn">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card vicoba-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table vicoba-table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Branch</th>
                        <th>Meeting</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td><code>{{ $group->code }}</code></td>
                            <td class="fw-medium">{{ $group->name }}</td>
                            <td>{{ $group->branch->name }}</td>
                            <td>{{ $group->meeting_day ?? '-' }}{{ $group->meeting_time ? ' at ' . $group->meeting_time : '' }}</td>
                            <td>
                                <span class="badge badge-status bg-{{ $group->status->color() }}">
                                    {{ $group->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('vicoba-groups.show', $group) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('vicoba-groups.edit', $group) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('vicoba-groups.destroy', $group) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this group?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No VICOBA groups found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($groups->hasPages())
        <div class="card-footer">
            {{ $groups->links() }}
        </div>
    @endif
</div>

@endsection
