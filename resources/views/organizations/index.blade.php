@extends('layouts.app')

@section('title', 'Organizations - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Organizations',
        'subtitle' => 'Manage organizations',
        'breadcrumb' => [
            ['label' => 'Organizations'],
        ],
        'actions' => '<a href="' . route('organizations.create') . '" class="btn btn-primary vicoba-btn"><i class="bi bi-plus-lg me-1"></i> Create Organization</a>',
    ])
@endsection

@section('content')

<div class="card vicoba-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('organizations.index') }}" class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Search by name, reg. number, email..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\OrganizationStatus::cases() as $status)
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
                <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary w-100 vicoba-btn">Clear</a>
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
                        <th>Name</th>
                        <th>Reg. Number</th>
                        <th>Phone</th>
                        <th>Region</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($organizations as $org)
                        <tr>
                            <td>
                                <div>
                                    <div class="fw-medium">{{ $org->name }}</div>
                                    <small class="text-muted">{{ $org->email ?? '-' }}</small>
                                </div>
                            </td>
                            <td><code>{{ $org->registration_number }}</code></td>
                            <td>{{ $org->phone ?? '-' }}</td>
                            <td>{{ $org->region ?? '-' }}{{ $org->district ? ', ' . $org->district : '' }}</td>
                            <td>
                                <span class="badge badge-status bg-{{ $org->status->color() }}">
                                    {{ $org->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('organizations.show', $org) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('organizations.edit', $org) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="{{ route('organizations.users', $org) }}" class="btn btn-outline-info" title="Users">
                                        <i class="bi bi-people"></i>
                                    </a>
                                    <form action="{{ route('organizations.destroy', $org) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this organization?')">
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
                                No organizations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($organizations->hasPages())
        <div class="card-footer">
            {{ $organizations->links() }}
        </div>
    @endif
</div>

@endsection
