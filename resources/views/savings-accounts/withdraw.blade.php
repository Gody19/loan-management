@extends('layouts.app')

@section('title', 'Withdraw')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Make Withdrawal',
        'subtitle' => 'Account: ' . $account->account_number . ' | Balance: ' . number_format($account->balance, 2),
        'breadcrumb' => [
            ['label' => 'Member Savings Accounts', 'url' => route('savings-accounts.index')],
            ['label' => $account->account_number, 'url' => route('savings-accounts.show', $account)],
            ['label' => 'Withdraw'],
        ],
        'actions' => '<a href="' . route('savings-accounts.show', $account) . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Account
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('savings-accounts.withdraw.store', $account) }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-6">

            {{-- Withdrawal Information --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-dash-circle me-2"></i>Withdrawal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" value="{{ old('amount') }}" step="0.01" min="0.01" max="{{ $account->balance }}" required placeholder="0.00">
                            @error('amount') <span class="text-danger small">{{ $message }}</span> @enderror
                            <div class="form-text">
                                Available balance: {{ number_format($account->balance, 2) }}
                                @if($account->savingsProduct->withdrawal_limit)
                                    | Max per withdrawal: {{ number_format($account->savingsProduct->withdrawal_limit, 2) }}
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="">Select Method</option>
                                <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="mobile_money" {{ old('payment_method') == 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                                <option value="cheque" {{ old('payment_method') == 'cheque' ? 'selected' : '' }}>Cheque</option>
                            </select>
                            @error('payment_method') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                            @error('transaction_date') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="e.g. Receipt number">
                            @error('reference') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional notes about this withdrawal">{{ old('description') }}</textarea>
                            @error('description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('savings-accounts.show', $account) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-warning px-4">
                    <i class="bi bi-dash-circle me-1"></i> Process Withdrawal
                </button>
            </div>

        </div>
    </div>
</form>
@endsection
