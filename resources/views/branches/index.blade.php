@extends('layouts.app')

@section('title', 'Branches - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Branches',
        'subtitle' => 'Manage branches',
        'breadcrumb' => [
            ['label' => 'Branches'],
        ],
        'actions' => '<a href="' . route('branches.create') . '" class="btn btn-primary vicoba-btn"><i class="bi bi-plus-lg me-1"></i> Create Branch</a>',
    ])
@endsection

@section('content')

<div class="card vicoba-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('branches.index') }}" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search by name, code..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="organization_id">
                    <option value="">All Organizations</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" {{ request('organization_id') == $org->id ? 'selected' : '' }}>
                            {{ $org->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\BranchStatus::cases() as $status)
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
                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary w-100 vicoba-btn">Clear</a>
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
                        <th>Organization</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branches as $branch)
                        <tr>
                            <td><code>{{ $branch->code }}</code></td>
                            <td class="fw-medium">{{ $branch->name }}</td>
                            <td>{{ $branch->organization->name }}</td>
                            <td>{{ $branch->manager ?? '-' }}</td>
                            <td>
                                <span class="badge badge-status bg-{{ $branch->status->color() }}">
                                    {{ $branch->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('branches.show', $branch) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('branches.edit', $branch) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="{{ route('branches.users', $branch) }}" class="btn btn-outline-info" title="Users">
                                        <i class="bi bi-people"></i>
                                    </a>
                                    <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="d-inline"
                                          data-confirm="Are you sure you want to delete this branch?">
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
                                No branches found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($branches->hasPages())
        <div class="card-footer">
            {{ $branches->links() }}
        </div>
    @endif
</div>

@endsection
