@extends('layouts.app')

@section('title', 'Savings Transactions')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Savings Transactions',
        'subtitle' => 'View all savings transactions',
        'breadcrumb' => [
            ['label' => 'Savings Transactions'],
        ],
        'actions' => ''
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        {{-- Filters --}}
        <form method="GET" action="{{ route('savings-transactions.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-2">
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="deposit" {{ request('type') == 'deposit' ? 'selected' : '' }}>Deposit</option>
                        <option value="withdrawal" {{ request('type') == 'withdrawal' ? 'selected' : '' }}>Withdrawal</option>
                        <option value="interest" {{ request('type') == 'interest' ? 'selected' : '' }}>Interest</option>
                        <option value="fee" {{ request('type') == 'fee' ? 'selected' : '' }}>Fee</option>
                        <option value="adjustment" {{ request('type') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="reversed" {{ request('status') == 'reversed' ? 'selected' : '' }}>Reversed</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="From Date">
                </div>
                <div class="col-lg-2">
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="To Date">
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('savings-transactions.index') }}" class="btn btn-outline-secondary">
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
                        <th>Transaction No</th>
                        <th>Member</th>
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
                    @forelse($transactions as $transaction)
                        <tr>
                            <td><span class="badge bg-primary">{{ $transaction->transaction_number }}</span></td>
                            <td>
                                <div class="fw-medium">{{ $transaction->savingsAccount->member->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $transaction->savingsAccount->account_number ?? '' }}</small>
                            </td>
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
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-list-ul fs-1 d-block mb-2"></i>
                                No transactions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} transactions
            </div>
            {{ $transactions->links() }}
        </div>

    </div>
</div>
@endsection
