@extends('layouts.member')

@section('title', 'My Shares - FinancePro VICOBA')
@section('page-title', 'My Shares')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="stat-label">Total Shares</div>
                        <div class="stat-value">{{ number_format($accounts->sum('total_shares'), 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-currency-dollar"></i></div>
                    <div>
                        <div class="stat-label">Total Share Value</div>
                        <div class="stat-value">TSh {{ number_format($accounts->sum('total_value'), 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($accounts->where('status.value', 'active')->count() > 0)
    <div class="col-xl-4 col-md-12">
        <div class="card vicoba-card h-100">
            <div class="card-body d-flex align-items-center justify-content-center gap-2">
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#purchaseModal">
                    <i class="bi bi-plus-lg me-1"></i> Purchase
                </button>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#redeemModal">
                    <i class="bi bi-dash-lg me-1"></i> Redeem
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

@if($accounts->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2 text-warning"></i>Share Accounts</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account Number</th>
                        <th>Plan</th>
                        <th class="text-end">Shares</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                    <tr>
                        <td class="fw-medium">{{ $account->account_number }}</td>
                        <td>{{ $account->product->name ?? '-' }}</td>
                        <td class="text-end">{{ number_format($account->total_shares, 0) }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($account->total_value, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $account->status->value === 'active' ? 'success' : 'secondary' }}">
                                {{ $account->status->label() }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($recentTransactions->count() > 0)
<div class="card vicoba-card">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Share Activity</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTransactions as $tx)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tx['date'])->format('d M Y') }}</td>
                        <td class="fw-medium">{{ $tx['transaction_number'] }}</td>
                        <td>{{ $tx['type'] }}</td>
                        <td class="text-end">{{ number_format($tx['quantity'], 0) }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($tx['amount'], 2) }}</td>
                        <td>
                            @php
                                $statusColors = ['Completed' => 'success', 'Pending' => 'warning', 'Reversed' => 'danger'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$tx['status']] ?? 'secondary' }}">
                                {{ $tx['status'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@else
<div class="card vicoba-card">
    <div class="card-body text-center py-5">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-cash-stack text-muted" style="font-size: 2rem;"></i>
        </div>
        <h6 class="text-muted fw-semibold">No share accounts</h6>
        <p class="text-muted small mb-0">You do not have any share accounts yet.</p>
    </div>
</div>
@endif

{{-- Purchase Modal --}}
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.shares.purchase') }}" id="purchaseForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="purchaseModalLabel"><i class="bi bi-plus-circle me-2 text-warning"></i>Purchase Shares</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="purchase_account" class="form-label">Share Account <span class="text-danger">*</span></label>
                        <select class="form-select @error('share_account_id') is-invalid @enderror"
                                id="purchase_account" name="share_account_id" required>
                            <option value="">Select account...</option>
                            @foreach($accounts->where('status.value', 'active') as $account)
                                <option value="{{ $account->id }}"
                                    data-price="{{ $account->product->share_price ?? 0 }}"
                                    data-shares="{{ $account->total_shares }}"
                                    {{ old('share_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_number }} - {{ $account->product->name ?? 'Shares' }} ({{ number_format($account->total_shares, 0) }} shares)
                                </option>
                            @endforeach
                        </select>
                        @error('share_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="purchase_quantity" class="form-label">Number of Shares <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" class="form-control @error('quantity') is-invalid @enderror"
                               id="purchase_quantity" name="quantity"
                               value="{{ old('quantity', 1) }}" required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="purchasePriceInfo"></div>
                    </div>

                    <div class="mb-3">
                        <label for="purchase_payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select @error('payment_method_id') is-invalid @enderror"
                                id="purchase_payment_method" name="payment_method_id" required>
                            <option value="">Select payment method...</option>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}" {{ old('payment_method_id') == $method->id ? 'selected' : '' }}>
                                    {{ $method->name }} ({{ $method->type->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('payment_method_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="purchase_date" class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('transaction_date') is-invalid @enderror"
                               id="purchase_date" name="transaction_date"
                               value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                        @error('transaction_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="purchase_reference" class="form-label">Reference</label>
                        <input type="text" class="form-control @error('reference') is-invalid @enderror"
                               id="purchase_reference" name="reference"
                               value="{{ old('reference') }}" placeholder="e.g. Receipt number">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="purchase_description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="purchase_description" name="description" rows="2"
                                  placeholder="Optional note">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="purchaseSubmit">
                        <i class="bi bi-check-lg me-1"></i> Confirm Purchase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Redeem Modal --}}
<div class="modal fade" id="redeemModal" tabindex="-1" aria-labelledby="redeemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.shares.redeem') }}" id="redeemForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="redeemModalLabel"><i class="bi bi-dash-circle me-2 text-danger"></i>Redeem Shares</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="redeem_account" class="form-label">Share Account <span class="text-danger">*</span></label>
                        <select class="form-select @error('share_account_id') is-invalid @enderror"
                                id="redeem_account" name="share_account_id" required>
                            <option value="">Select account...</option>
                            @foreach($accounts->where('status.value', 'active')->where('total_shares', '>', 0) as $account)
                                <option value="{{ $account->id }}"
                                    data-shares="{{ $account->total_shares }}"
                                    data-price="{{ $account->product->share_price ?? 0 }}"
                                    {{ old('share_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_number }} - {{ $account->product->name ?? 'Shares' }} ({{ number_format($account->total_shares, 0) }} shares)
                                </option>
                            @endforeach
                        </select>
                        @error('share_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="redeem_quantity" class="form-label">Number of Shares to Redeem <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" class="form-control @error('quantity') is-invalid @enderror"
                               id="redeem_quantity" name="quantity"
                               value="{{ old('quantity', 1) }}" required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="redeemInfo"></div>
                    </div>

                    <div class="mb-3">
                        <label for="redeem_payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select @error('payment_method_id') is-invalid @enderror"
                                id="redeem_payment_method" name="payment_method_id" required>
                            <option value="">Select payment method...</option>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}" {{ old('payment_method_id') == $method->id ? 'selected' : '' }}>
                                    {{ $method->name }} ({{ $method->type->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('payment_method_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="redeem_date" class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('transaction_date') is-invalid @enderror"
                               id="redeem_date" name="transaction_date"
                               value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                        @error('transaction_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="redeem_reference" class="form-label">Reference</label>
                        <input type="text" class="form-control @error('reference') is-invalid @enderror"
                               id="redeem_reference" name="reference"
                               value="{{ old('reference') }}" placeholder="e.g. Receipt number">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="redeem_description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="redeem_description" name="description" rows="2"
                                  placeholder="Optional note">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="redeemSubmit">
                        <i class="bi bi-check-lg me-1"></i> Confirm Redemption
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const purchaseAccount = document.getElementById('purchase_account');
    const purchaseQuantity = document.getElementById('purchase_quantity');
    const purchasePriceInfo = document.getElementById('purchasePriceInfo');

    function updatePurchaseInfo() {
        const selected = purchaseAccount.options[purchaseAccount.selectedIndex];
        const price = parseFloat(selected.getAttribute('data-price') || 0);
        const qty = parseInt(purchaseQuantity.value || 0);
        const total = price * qty;
        if (price > 0) {
            purchasePriceInfo.textContent = 'Share price: TSh ' + price.toLocaleString('en', {minimumFractionDigits: 2}) + ' x ' + qty + ' = TSh ' + total.toLocaleString('en', {minimumFractionDigits: 2});
        } else {
            purchasePriceInfo.textContent = '';
        }
    }

    if (purchaseAccount) {
        purchaseAccount.addEventListener('change', updatePurchaseInfo);
    }
    if (purchaseQuantity) {
        purchaseQuantity.addEventListener('input', updatePurchaseInfo);
    }

    const redeemAccount = document.getElementById('redeem_account');
    const redeemQuantity = document.getElementById('redeem_quantity');
    const redeemInfo = document.getElementById('redeemInfo');

    function updateRedeemInfo() {
        const selected = redeemAccount.options[redeemAccount.selectedIndex];
        const available = parseInt(selected.getAttribute('data-shares') || 0);
        const price = parseFloat(selected.getAttribute('data-price') || 0);
        const qty = parseInt(redeemQuantity.value || 0);
        if (available > 0) {
            redeemInfo.textContent = 'Available: ' + available + ' shares. Redemption value: TSh ' + (qty * price).toLocaleString('en', {minimumFractionDigits: 2});
        } else {
            redeemInfo.textContent = '';
        }
    }

    if (redeemAccount) {
        redeemAccount.addEventListener('change', updateRedeemInfo);
    }
    if (redeemQuantity) {
        redeemQuantity.addEventListener('input', updateRedeemInfo);
    }

    ['purchaseForm', 'redeemForm'].forEach(function(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function() {
                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
            });
        }
    });
});
</script>
@endpush
