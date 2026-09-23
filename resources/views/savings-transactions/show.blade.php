@extends('layouts.app')

@section('title', 'Transaction Details')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Transaction: ' . $transaction->transaction_number,
        'subtitle' => $transaction->transaction_type->label() . ' | ' . $transaction->status->label(),
        'breadcrumb' => [
            ['label' => 'Savings Transactions', 'url' => route('savings-transactions.index')],
            ['label' => $transaction->transaction_number],
        ],
        'actions' => '<a href="' . route('savings-transactions.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
        {{-- Transaction Summary Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-{{ $transaction->transaction_type->value === 'deposit' ? 'success' : ($transaction->transaction_type->value === 'withdrawal' ? 'warning' : 'primary') }} rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-{{ $transaction->transaction_type->value === 'deposit' ? 'plus-circle' : ($transaction->transaction_type->value === 'withdrawal' ? 'dash-circle' : 'arrow-left-right') }} text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $transaction->transaction_number }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-{{ $transaction->transaction_type->value === 'deposit' ? 'success' : ($transaction->transaction_type->value === 'withdrawal' ? 'warning' : 'info') }}">{{ $transaction->transaction_type->label() }}</span>
                            <span class="badge bg-{{ $transaction->status->value === 'completed' ? 'success' : ($transaction->status->value === 'pending' ? 'warning' : 'danger') }}">{{ $transaction->status->label() }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Amount</div>
                        <div class="fw-bold fs-4 text-{{ $transaction->transaction_type->value === 'deposit' ? 'success' : 'warning' }}">
                            {{ $transaction->transaction_type->value === 'deposit' ? '+' : '-' }}{{ number_format($transaction->amount, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- Transaction Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Transaction Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Transaction No</td><td class="fw-medium">{{ $transaction->transaction_number }}</td></tr>
                            <tr><td class="text-muted">Type</td><td><span class="badge bg-{{ $transaction->transaction_type->value === 'deposit' ? 'success' : ($transaction->transaction_type->value === 'withdrawal' ? 'warning' : 'info') }}">{{ $transaction->transaction_type->label() }}</span></td></tr>
                            <tr><td class="text-muted">Amount</td><td class="fw-bold">{{ number_format($transaction->amount, 2) }}</td></tr>
                            <tr><td class="text-muted">Payment Method</td><td class="fw-medium">{{ ucfirst(str_replace('_', ' ', $transaction->payment_method ?? '—')) }}</td></tr>
                            <tr><td class="text-muted">Reference</td><td class="fw-medium">{{ $transaction->reference ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Description</td><td class="fw-medium">{{ $transaction->description ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $transaction->status->value === 'completed' ? 'success' : ($transaction->status->value === 'pending' ? 'warning' : 'danger') }}">{{ $transaction->status->label() }}</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Account & Balance --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2"></i>Account & Balance</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Account Number</td><td class="fw-medium">{{ $transaction->savingsAccount->account_number ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Member</td><td class="fw-medium">{{ $transaction->savingsAccount->member->full_name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Plan</td><td class="fw-medium">{{ $transaction->savingsAccount->savingsProduct->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Balance Before</td><td class="fw-medium">{{ number_format($transaction->balance_before, 2) }}</td></tr>
                            <tr><td class="text-muted">Balance After</td><td class="fw-bold text-primary">{{ number_format($transaction->balance_after, 2) }}</td></tr>
                            <tr><td class="text-muted">Transaction Date</td><td class="fw-medium">{{ $transaction->transaction_date?->format('d M Y H:i') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Processed By</td><td class="fw-medium">{{ $transaction->processor->fullname ?? 'System' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

        </div>
@endsection
