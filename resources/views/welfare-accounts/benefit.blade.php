@extends('layouts.app')

@section('title', 'Welfare Benefit')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Welfare Benefit',
        'subtitle' => 'Account: ' . $account->account_number . ' | ' . ($account->member->full_name ?? '—'),
        'breadcrumb' => [
            ['label' => 'Member Welfare Accounts', 'url' => route('welfare-accounts.index')],
            ['label' => $account->account_number, 'url' => route('welfare-accounts.show', $account)],
            ['label' => 'Benefit'],
        ],
        'actions' => '<a href="' . route('welfare-accounts.show', $account) . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Account
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('welfare-accounts.benefit.store', $account) }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-dash-circle me-2"></i>Benefit Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required>
                            @error('amount') <span class="text-danger small">{{ $message }}</span> @enderror
                            <div class="form-text">Available balance: {{ number_format($account->current_balance, 2) }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                            @error('transaction_date') <span class="text-danger small">{{ $message }}</span> @enderror
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
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Reason for benefit payout">{{ old('description') }}</textarea>
                            @error('description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('welfare-accounts.show', $account) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-warning px-4">
                    <i class="bi bi-dash-circle me-1"></i> Submit Benefit
                </button>
            </div>

        </div>
    </div>
</form>
@endsection
