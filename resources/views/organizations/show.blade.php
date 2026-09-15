@extends('layouts.app')

@section('title', $organization->name . ' - ' . config('app.name'))

@section('breadcrumb')
    @include('layouts.components.breadcrumb', ['breadcrumbs' => ['Dashboard' => route('dashboard'), 'Organizations' => route('organizations.index'), $organization->name => '']])
@endsection

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $organization->name,
        'subtitle' => 'Organization Details',
        'actions' => '<a href="' . route('organizations.edit', $organization) . '" class="btn btn-primary vicoba-btn"><i class="bi bi-pencil me-1"></i> Edit</a>',
    ])
@endsection

@section('content')

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card vicoba-card mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Information</h6>
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
        <div class="card vicoba-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Users ({{ $organization->users->count() }})</h6>
                <a href="{{ route('organizations.users', $organization) }}" class="btn btn-sm btn-outline-primary vicoba-btn">Manage</a>
            </div>
            <div class="card-body">
                @forelse($organization->users->take(5) as $user)
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
                            <span class="text-white fw-semibold" style="font-size: 0.6rem;">{{ substr($user->fullname, 0, 1) }}</span>
                        </div>
                        <div>
                            <div class="fw-medium" style="font-size: 0.85rem;">{{ $user->fullname }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">No users assigned.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
