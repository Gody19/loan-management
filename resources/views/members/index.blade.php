@extends('layouts.app')

@section('title', 'Members')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Members',
        'subtitle' => 'Manage VICOBA members',
        'actions' => '<a href="' . route('members.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Member
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        {{-- Filters --}}
        <form method="GET" action="{{ route('members.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search members..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2">
                    <select name="organization_id" class="form-select">
                        <option value="">All Organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}" {{ request('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="branch_id" class="form-select">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="vicoba_group_id" class="form-select">
                        <option value="">All Groups</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" {{ request('vicoba_group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1">
                    <select name="membership_status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\MemberStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ request('membership_status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Member No.</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Organization</th>
                        <th>Branch</th>
                        <th>Group</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        <tr>
                            <td><span class="badge bg-primary">{{ $member->member_number }}</span></td>
                            <td>
                                <div class="fw-medium">{{ $member->full_name }}</div>
                                <small class="text-muted">{{ $member->email ?? '—' }}</small>
                            </td>
                            <td>{{ $member->phone }}</td>
                            <td>{{ $member->organization->name ?? '—' }}</td>
                            <td>{{ $member->branch->name ?? '—' }}</td>
                            <td>{{ $member->vicobaGroup->name ?? '—' }}</td>
                            <td>{{ $member->joining_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $member->membership_status->color() }}">
                                    {{ $member->membership_status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('members.show', $member) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @can('update', $member)
                                        <a href="{{ route('members.edit', $member) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No members found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $members->firstItem() ?? 0 }} to {{ $members->lastItem() ?? 0 }} of {{ $members->total() }} members
            </div>
            {{ $members->links() }}
        </div>

    </div>
</div>
@endsection
