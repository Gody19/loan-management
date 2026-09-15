@extends('layouts.app')

@section('title', 'Welfare Transaction Details')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Transaction: ' . $transaction->transaction_number,
        'subtitle' => $transaction->transaction_type->label() . ' | ' . $transaction->status->label(),
        'actions' => '<a href="' . route('welfare-transactions.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2"></i>Transaction Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Transaction Number</div>
                        <div class="fw-medium"><span class="badge bg-primary">{{ $transaction->transaction_number }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Transaction Type</div>
                        <div>
                            <span class="badge bg-{{ $transaction->transaction_type->isCredit() ? 'success' : 'warning' }}">
                                {{ $transaction->transaction_type->label() }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Status</div>
                        <div>
                            <span class="badge bg-{{ $transaction->status->color() }}">
                                {{ $transaction->status->label() }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Amount</div>
                        <div class="fs-5 fw-bold text-primary">{{ number_format($transaction->amount, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Balance Before</div>
                        <div class="fw-medium">{{ number_format($transaction->balance_before, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Balance After</div>
                        <div class="fw-medium">{{ number_format($transaction->balance_after, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Transaction Date</div>
                        <div class="fw-medium">{{ $transaction->transaction_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Created At</div>
                        <div class="fw-medium">{{ $transaction->created_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Created By</div>
                        <div class="fw-medium">{{ $transaction->creator->fullname ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Member & Account</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Member</div>
                        <div class="fw-medium">{{ $transaction->member->full_name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Account Number</div>
                        <div class="fw-medium">
                            @if($transaction->account)
                                <a href="{{ route('welfare-accounts.show', $transaction->account) }}">{{ $transaction->account->account_number }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Welfare Fund</div>
                        <div class="fw-medium">{{ $transaction->account->fund->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $transaction->organization->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Branch</div>
                        <div class="fw-medium">{{ $transaction->branch->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Group</div>
                        <div class="fw-medium">{{ $transaction->vicobaGroup->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-credit-card me-2"></i>Payment Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Payment Method</div>
                        <div class="fw-medium">{{ $transaction->paymentMethod->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Reference</div>
                        <div class="fw-medium">{{ $transaction->reference ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Description</div>
                        <div class="fw-medium">{{ $transaction->description ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
