@extends('layouts.app')

@section('title', 'Savings Product')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $product->name,
        'subtitle' => 'Product Code: ' . $product->code . ' | ' . ucfirst($product->status),
        'actions' => '<a href="' . route('savings-products.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <a href="' . route('savings-products.edit', $product) . '" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Product Summary Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-piggy-bank text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $product->name }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-primary">{{ $product->code }}</span>
                            <span class="badge bg-{{ $product->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($product->status) }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $product->organization->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- Product Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Product Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Name</td><td class="fw-medium">{{ $product->name }}</td></tr>
                            <tr><td class="text-muted">Code</td><td class="fw-medium">{{ $product->code }}</td></tr>
                            <tr><td class="text-muted">Organization</td><td class="fw-medium">{{ $product->organization->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Description</td><td class="fw-medium">{{ $product->description ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $product->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($product->status) }}</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Amount Configuration --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-currency-dollar me-2"></i>Amount Configuration</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Minimum Amount</td><td class="fw-medium">{{ number_format($product->minimum_amount, 2) }}</td></tr>
                            <tr><td class="text-muted">Maximum Amount</td><td class="fw-medium">{{ number_format($product->maximum_amount, 2) }}</td></tr>
                            <tr><td class="text-muted">Minimum Balance</td><td class="fw-medium">{{ number_format($product->minimum_balance ?? 0, 2) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Withdrawal Settings --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-arrow-up-right me-2"></i>Withdrawal Settings</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Allow Withdrawals</td><td class="fw-medium">{{ $product->allow_withdrawal ? 'Yes' : 'No' }}</td></tr>
                            <tr><td class="text-muted">Withdrawal Limit</td><td class="fw-medium">{{ $product->withdrawal_limit ? number_format($product->withdrawal_limit, 2) : 'No limit' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Accounts Using This Product --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2"></i>Accounts Using This Product</h6>
                    </div>
                    <div class="card-body">
                        @if($product->accounts->count())
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Account No</th>
                                            <th>Member</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($product->accounts as $account)
                                            <tr>
                                                <td><span class="badge bg-primary">{{ $account->account_number }}</span></td>
                                                <td>{{ $account->member->full_name ?? '—' }}</td>
                                                <td class="fw-medium">{{ number_format($account->balance, 2) }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $account->status === 'active' ? 'success' : 'secondary' }}">
                                                        {{ ucfirst($account->status) }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="{{ route('savings-accounts.show', $account) }}" class="btn btn-sm btn-outline-primary">
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
                                <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                                No accounts using this product yet.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
