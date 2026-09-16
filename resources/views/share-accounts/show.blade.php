@extends('layouts.app')

@section('title', 'Share Account Details')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Account: ' . $account->account_number,
        'subtitle' => $account->member->full_name ?? '—' . ' | ' . $account->status->label(),
        'breadcrumb' => [
            ['label' => 'Member Share Accounts', 'url' => route('share-accounts.index')],
            ['label' => $account->account_number],
        ],
        'actions' => '<a href="' . route('share-accounts.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        @if($account->status->value === "active")
        <a href="' . route('share-accounts.purchase', $account) . '" class="btn btn-success">
            <i class="bi bi-cart-plus me-1"></i> Purchase
        </a>
        <a href="' . route('share-accounts.redeem', $account) . '" class="btn btn-warning">
            <i class="bi bi-cart-dash me-1"></i> Redeem
        </a>
        @endif'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Total Shares</div>
                        <div class="fs-4 fw-bold text-primary">{{ number_format($account->total_shares) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Total Value</div>
                        <div class="fs-4 fw-bold">{{ number_format($account->total_value, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Share Price</div>
                        <div class="fs-4 fw-bold">{{ number_format($account->product->share_price ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Transactions</div>
                        <div class="fs-4 fw-bold">{{ $account->transactions()->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Account Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Account Number</div>
                        <div class="fw-medium"><span class="badge bg-primary">{{ $account->account_number }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Member</div>
                        <div class="fw-medium">{{ $account->member->full_name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Share Plan</div>
                        <div class="fw-medium">{{ $account->product->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $account->organization->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Branch</div>
                        <div class="fw-medium">{{ $account->branch->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Group</div>
                        <div class="fw-medium">{{ $account->vicobaGroup->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Status</div>
                        <div><span class="badge bg-{{ $account->status->color() }}">{{ $account->status->label() }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Created</div>
                        <div class="fw-medium">{{ $account->created_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-arrow-left-right me-2"></i>Transactions ({{ $account->transactions()->count() }})</h6>
            </div>
            <div class="card-body">
                @if($account->transactions->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Transaction No.</th>
                                    <th>Type</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($account->transactions->sortByDesc('transaction_date') as $transaction)
                                    <tr>
                                        <td><span class="badge bg-primary">{{ $transaction->transaction_number }}</span></td>
                                        <td>
                                            <span class="badge bg-{{ $transaction->transaction_type->value === 'purchase' ? 'success' : 'warning' }}">
                                                {{ $transaction->transaction_type->label() }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ number_format($transaction->quantity) }}</td>
                                        <td class="text-end">{{ number_format($transaction->share_price, 2) }}</td>
                                        <td class="text-end fw-medium">{{ number_format($transaction->amount, 2) }}</td>
                                        <td>{{ $transaction->transaction_date?->format('d M Y') ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $transaction->status->color() }}">
                                                {{ $transaction->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('share-transactions.show', $transaction) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-arrow-left-right fs-1 d-block mb-2"></i>
                        No transactions yet.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
