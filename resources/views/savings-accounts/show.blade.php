@extends('layouts.app')

@section('title', 'Savings Account')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Account: ' . $account->account_number,
        'subtitle' => $account->member->full_name ?? '' . ' | ' . ucfirst($account->status),
        'actions' => '<a href="' . route('savings-accounts.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Account Summary Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-wallet2 text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $account->account_number }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-primary">{{ $account->savingsProduct->name ?? '—' }}</span>
                            <span class="badge bg-{{ $account->status === 'active' ? 'success' : ($account->status === 'closed' ? 'danger' : 'secondary') }}">{{ ucfirst($account->status) }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Current Balance</div>
                        <div class="fw-bold fs-4 text-primary">{{ number_format($account->balance, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        @if($account->status === 'active')
        <div class="d-flex gap-2 mb-4">
            <a href="{{ route('savings-accounts.deposit', $account) }}" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Deposit
            </a>
            @if($account->savingsProduct->allow_withdrawal)
                <a href="{{ route('savings-accounts.withdraw', $account) }}" class="btn btn-warning">
                    <i class="bi bi-dash-circle me-1"></i> Withdraw
                </a>
            @endif
        </div>
        @endif

        <div class="row g-4">

            {{-- Account Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Account Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Account Number</td><td class="fw-medium">{{ $account->account_number }}</td></tr>
                            <tr><td class="text-muted">Member</td><td class="fw-medium">{{ $account->member->full_name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Plan</td><td class="fw-medium">{{ $account->savingsProduct->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Organization</td><td class="fw-medium">{{ $account->organization->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Branch</td><td class="fw-medium">{{ $account->branch->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Group</td><td class="fw-medium">{{ $account->vicobaGroup->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Opening Date</td><td class="fw-medium">{{ $account->opening_date?->format('d M Y') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $account->status === 'active' ? 'success' : ($account->status === 'closed' ? 'danger' : 'secondary') }}">{{ ucfirst($account->status) }}</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Balance Summary --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2"></i>Balance Summary</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Current Balance</td><td class="fw-bold text-primary">{{ number_format($account->balance, 2) }}</td></tr>
                            <tr><td class="text-muted">Total Deposits</td><td class="fw-medium">{{ number_format($account->total_deposits ?? 0, 2) }}</td></tr>
                            <tr><td class="text-muted">Total Withdrawals</td><td class="fw-medium">{{ number_format($account->total_withdrawals ?? 0, 2) }}</td></tr>
                            <tr><td class="text-muted">Transactions</td><td class="fw-medium">{{ $account->transactions_count ?? $account->transactions->count() }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- Transactions Table --}}
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Recent Transactions</h6>
            </div>
            <div class="card-body">
                @if($transactions->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Transaction No</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Balance Before</th>
                                    <th>Balance After</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    <tr>
                                        <td><span class="badge bg-primary">{{ $transaction->transaction_number }}</span></td>
                                        <td>
                                            <span class="badge bg-{{ $transaction->type === 'deposit' ? 'success' : ($transaction->type === 'withdrawal' ? 'warning' : 'info') }}">
                                                {{ ucfirst($transaction->type) }}
                                            </span>
                                        </td>
                                        <td class="fw-medium">{{ number_format($transaction->amount, 2) }}</td>
                                        <td>{{ number_format($transaction->balance_before, 2) }}</td>
                                        <td>{{ number_format($transaction->balance_after, 2) }}</td>
                                        <td>{{ $transaction->transaction_date?->format('d M Y') ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $transaction->status === 'completed' ? 'success' : ($transaction->status === 'pending' ? 'warning' : 'danger') }}">
                                                {{ ucfirst($transaction->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('savings-transactions.show', $transaction) }}" class="btn btn-sm btn-outline-primary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted" style="font-size: 0.875rem;">
                            Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} transactions
                        </div>
                        {{ $transactions->links() }}
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-list-ul fs-1 d-block mb-2"></i>
                        No transactions yet.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
