@extends('layouts.app')

@section('title', 'Welfare Transactions')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Welfare Transactions',
        'subtitle' => 'View all welfare contributions and benefit transactions',
        'breadcrumb' => [
            ['label' => 'Welfare Transactions'],
        ]
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('welfare-transactions.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search transactions..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2">
                    <select name="transaction_type" class="form-select">
                        <option value="">All Types</option>
                        @foreach(\App\Enums\WelfareTransactionType::cases() as $type)
                            <option value="{{ $type->value }}" {{ request('transaction_type') == $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\FinancialTransactionStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="From">
                </div>
                <div class="col-lg-2">
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="To">
                </div>
                <div class="col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('welfare-transactions.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Transaction No.</th>
                        <th>Member</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance</th>
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
                                <div class="fw-medium">{{ $transaction->member->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $transaction->account->account_number ?? '' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-{{ $transaction->transaction_type->isCredit() ? 'success' : 'warning' }}">
                                    {{ $transaction->transaction_type->label() }}
                                </span>
                            </td>
                            <td class="text-end fw-medium">{{ number_format($transaction->amount, 2) }}</td>
                            <td class="text-end">{{ number_format($transaction->balance_after, 2) }}</td>
                            <td>{{ $transaction->transaction_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $transaction->status->color() }}">
                                    {{ $transaction->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('welfare-transactions.show', $transaction) }}" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-arrow-left-right fs-1 d-block mb-2"></i>
                                No welfare transactions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} transactions
            </div>
            {{ $transactions->links() }}
        </div>

    </div>
</div>
@endsection
