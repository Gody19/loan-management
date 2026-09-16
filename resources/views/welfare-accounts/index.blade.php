@extends('layouts.app')

@section('title', 'Member Welfare Accounts')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Member Welfare Accounts',
        'subtitle' => 'Manage member welfare accounts',
        'breadcrumb' => [
            ['label' => 'Member Welfare Accounts'],
        ],
        'actions' => '<a href="' . route('welfare-accounts.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Open Account
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('welfare-accounts.index') }}" class="mb-4">
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
                    <select name="welfare_fund_id" class="form-select">
                        <option value="">All Funds</option>
                        @foreach($funds as $fund)
                            <option value="{{ $fund->id }}" {{ request('welfare_fund_id') == $fund->id ? 'selected' : '' }}>{{ $fund->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\WelfareAccountStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('welfare-accounts.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account No.</th>
                        <th>Member</th>
                        <th>Fund</th>
                        <th class="text-end">Balance</th>
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
                            <td>{{ $account->fund->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($account->current_balance, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $account->status->color() }}">
                                    {{ $account->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('welfare-accounts.show', $account) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                                No welfare accounts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }} accounts
            </div>
            {{ $accounts->links() }}
        </div>

    </div>
</div>
@endsection
