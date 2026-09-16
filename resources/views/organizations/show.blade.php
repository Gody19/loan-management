@extends('layouts.app')

@section('title', $organization->name . ' - ' . config('app.name'))

@section('page-header')
    @php
        $headerActions = '<a href="' . route('organizations.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>';
        if (auth()->user()->can('update', $organization)) {
            $headerActions .= '<a href="' . route('organizations.edit', $organization) . '" class="btn btn-primary vicoba-btn">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>';
        }
    @endphp
    @include('layouts.components.page-header', [
        'title' => $organization->name,
        'subtitle' => 'Organization Details',
        'breadcrumb' => [
            ['label' => 'Organizations', 'url' => route('organizations.index')],
            ['label' => $organization->name],
        ],
        'actions' => $headerActions,
    ])
@endsection

@section('content')

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Organization Information --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Organization Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3" style="font-size: 0.9rem;">
                    <div class="col-md-6">
                        <span class="text-muted d-block">Registration Number</span>
                        <span class="fw-medium"><code>{{ $organization->registration_number }}</code></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Status</span>
                        <span class="badge bg-{{ $organization->status->color() }}">{{ $organization->status->label() }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Phone</span>
                        <span>{{ $organization->phone ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Email</span>
                        <span>{{ $organization->email ?? '-' }}</span>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted d-block">Address</span>
                        <span>{{ $organization->address ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Region</span>
                        <span>{{ $organization->region ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">District</span>
                        <span>{{ $organization->district ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Created</span>
                        <span>{{ $organization->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Organization Administrators --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Organization Administrators ({{ $administrators->count() }})</h6>
                @if(auth()->user()->can('update', $organization))
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignAdminModal">
                        <i class="bi bi-person-plus me-1"></i> Assign Admin
                    </button>
                @endif
            </div>
            <div class="card-body p-0">
                @if($administrators->isEmpty())
                    <p class="text-muted text-center py-3 mb-0">No administrators assigned.</p>
                @else
                    <div class="table-responsive">
                        <table class="table vicoba-table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                    @if(auth()->user()->can('update', $organization))
                                        <th class="text-end">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($administrators as $admin)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <span class="text-white fw-semibold" style="font-size: 0.7rem;">{{ substr($admin->fullname, 0, 1) }}</span>
                                                </div>
                                                <span class="fw-medium">{{ $admin->fullname }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $admin->email }}</td>
                                        <td>{{ $admin->phone ?? '-' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $admin->status === \App\Enums\UserStatus::Active ? 'success' : 'secondary' }}">
                                                {{ $admin->status->label() }}
                                            </span>
                                        </td>
                                        <td>{{ $admin->pivot->created_at?->format('M d, Y') ?? '-' }}</td>
                                        @if(auth()->user()->can('update', $organization))
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('organizations.remove-admin', [$organization, $admin]) }}" class="d-inline"
                                                      onsubmit="return confirm('Remove this administrator? An organization must have at least one administrator.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Branches --}}
        <div class="card vicoba-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Branches ({{ $organization->branches->count() }})</h6>
                <a href="{{ route('branches.create') }}?organization_id={{ $organization->id }}" class="btn btn-sm btn-outline-primary vicoba-btn">
                    <i class="bi bi-plus-lg me-1"></i> Add Branch
                </a>
            </div>
            <div class="card-body p-0">
                @if($organization->branches->isEmpty())
                    <p class="text-muted text-center py-3 mb-0">No branches yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table vicoba-table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Manager</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($organization->branches as $branch)
                                    <tr>
                                        <td><code>{{ $branch->code }}</code></td>
                                        <td><a href="{{ route('branches.show', $branch) }}">{{ $branch->name }}</a></td>
                                        <td>{{ $branch->manager ?? '-' }}</td>
                                        <td><span class="badge bg-{{ $branch->status->color() }}">{{ $branch->status->label() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Quick Stats --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Quick Stats</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Branches</span>
                    <span class="fw-bold">{{ $organization->branches->count() }}</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">VICOBA Groups</span>
                    <span class="fw-bold">{{ $organization->vicobaGroups->count() ?? 0 }}</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Members</span>
                    <span class="fw-bold">{{ $organization->members->count() }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Administrators</span>
                    <span class="fw-bold">{{ $administrators->count() }}</span>
                </div>
            </div>
        </div>

        {{-- All Users --}}
        <div class="card vicoba-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">All Users ({{ $organization->users->count() }})</h6>
                <a href="{{ route('organizations.users', $organization) }}" class="btn btn-sm btn-outline-primary vicoba-btn">Manage</a>
            </div>
            <div class="card-body">
                @forelse($organization->users->take(10) as $user)
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
                            <span class="text-white fw-semibold" style="font-size: 0.6rem;">{{ substr($user->fullname, 0, 1) }}</span>
                        </div>
                        <div>
                            <div class="fw-medium" style="font-size: 0.85rem;">{{ $user->fullname }}</div>
                            <small class="text-muted">{{ $user->roles->pluck('name')->first() ?? 'No Role' }}</small>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">No users assigned.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Assign Admin Modal --}}
@if(auth()->user()->can('update', $organization))
<div class="modal fade" id="assignAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('organizations.assign-admin', $organization) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="admin_user_id" class="form-label">Select User <span class="text-danger">*</span></label>
                        <select name="admin_user_id" id="admin_user_id" class="form-select" required>
                            <option value="">Select a user...</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->fullname }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Administrator</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
