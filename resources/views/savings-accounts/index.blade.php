@extends('layouts.app')

@section('title', 'Member Savings Accounts')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Member Savings Accounts',
        'subtitle' => 'Manage member savings accounts',
        'breadcrumb' => [
            ['label' => 'Member Savings Accounts'],
        ],
        'actions' => '<a href="' . route('savings-accounts.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Open Account
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        {{-- Filters --}}
        <form method="GET" action="{{ route('savings-accounts.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search accounts..." value="{{ request('search') }}">
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
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('savings-accounts.index') }}" class="btn btn-outline-secondary">
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
                        <th>Account No</th>
                        <th>Member</th>
                        <th>Plan</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td><span class="badge bg-primary">{{ $account->account_number }}</span></td>
                            <td>
                                <div class="fw-medium">{{ $account->member->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $account->member->member_number ?? '' }}</small>
                            </td>
                            <td>{{ $account->savingsProduct->name ?? '—' }}</td>
                            <td class="fw-medium">{{ number_format($account->current_balance, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $account->status->color() }}">
                                    {{ $account->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('savings-accounts.show', $account) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                                No savings accounts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }} accounts
            </div>
            {{ $accounts->links() }}
        </div>

    </div>
</div>
@endsection
