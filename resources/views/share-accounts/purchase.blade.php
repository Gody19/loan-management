@extends('layouts.app')

@section('title', 'Purchase Shares')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Purchase Shares',
        'subtitle' => 'Account: ' . $account->account_number . ' | ' . ($account->member->full_name ?? '—'),
        'actions' => '<a href="' . route('share-accounts.show', $account) . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Account
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('share-accounts.purchase.store', $account) }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-cart-plus me-2"></i>Purchase Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Quantity (Shares) <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="quantity" id="quantity" class="form-control" value="{{ old('quantity') }}" required>
                            @error('quantity') <span class="text-danger small">{{ $message }}</span> @enderror
                            <div class="form-text">Min: {{ number_format($account->product->minimum_shares ?? 0) }} | Max: {{ number_format($account->product->maximum_shares ?? 0) }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Share Price</label>
                            <input type="text" class="form-control" value="{{ number_format($account->product->share_price ?? 0, 2) }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Amount</label>
                            <input type="text" id="totalAmount" class="form-control fw-bold" value="0.00" readonly>
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
                        <div class="col-md-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method_id" class="form-select" required>
                                <option value="">Select Method</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}" {{ old('payment_method_id') == $method->id ? 'selected' : '' }}>{{ $method->name }}</option>
                                @endforeach
                            </select>
                            @error('payment_method_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="Payment reference number">
                            @error('reference') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                            @error('transaction_date') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional transaction description">{{ old('description') }}</textarea>
                            @error('description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('share-accounts.show', $account) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-cart-plus me-1"></i> Purchase Shares
                </button>
            </div>

        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const totalAmountInput = document.getElementById('totalAmount');
    const sharePrice = {{ $account->product->share_price ?? 0 }};

    quantityInput.addEventListener('input', function() {
        const qty = parseInt(this.value) || 0;
        const total = qty * sharePrice;
        totalAmountInput.value = total.toFixed(2);
    });
});
</script>
@endpush
