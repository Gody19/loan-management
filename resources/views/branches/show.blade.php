@extends('layouts.app')

@section('title', $branch->name . ' - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $branch->name,
        'subtitle' => 'Branch Details — ' . $branch->organization->name,
        'breadcrumb' => [
            ['label' => 'Branches', 'url' => route('branches.index')],
            ['label' => $branch->name],
        ],
        'actions' => '<a href="' . route('branches.edit', $branch) . '" class="btn btn-primary vicoba-btn"><i class="bi bi-pencil me-1"></i> Edit</a>',
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
                        <span class="text-muted d-block">Branch Code</span>
                        <span class="fw-medium"><code>{{ $branch->code }}</code></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Status</span>
                        <span class="badge bg-{{ $branch->status->color() }}">{{ $branch->status->label() }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Organization</span>
                        <a href="{{ route('organizations.show', $branch->organization) }}">{{ $branch->organization->name }}</a>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Manager</span>
                        <span>{{ $branch->manager ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Phone</span>
                        <span>{{ $branch->phone ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Created</span>
                        <span>{{ $branch->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted d-block">Address</span>
                        <span>{{ $branch->address ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card vicoba-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">VICOBA Groups ({{ $branch->groups->count() }})</h6>
                <a href="{{ route('vicoba-groups.create') }}?branch_id={{ $branch->id }}" class="btn btn-sm btn-outline-primary vicoba-btn">
                    <i class="bi bi-plus-lg me-1"></i> Add Group
                </a>
            </div>
            <div class="card-body p-0">
                @if($branch->groups->isEmpty())
                    <p class="text-muted text-center py-3 mb-0">No VICOBA groups yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table vicoba-table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Meeting</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($branch->groups as $group)
                                    <tr>
                                        <td><code>{{ $group->code }}</code></td>
                                        <td><a href="{{ route('vicoba-groups.show', $group) }}">{{ $group->name }}</a></td>
                                        <td>{{ $group->meeting_day ?? '-' }}{{ $group->meeting_time ? ' at ' . $group->meeting_time : '' }}</td>
                                        <td><span class="badge bg-{{ $group->status->color() }}">{{ $group->status->label() }}</span></td>
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
                <h6 class="mb-0 fw-semibold">Users ({{ $branch->users->count() }})</h6>
                <a href="{{ route('branches.users', $branch) }}" class="btn btn-sm btn-outline-primary vicoba-btn">Manage</a>
            </div>
            <div class="card-body">
                @forelse($branch->users->take(5) as $user)
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
