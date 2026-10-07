@extends('layouts.app')

@section('title', 'Deposit')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Make Deposit',
        'subtitle' => 'Account: ' . $account->account_number . ' | Balance: ' . number_format($account->current_balance, 2),
        'breadcrumb' => [
            ['label' => 'Member Savings Accounts', 'url' => route('savings-accounts.index')],
            ['label' => $account->account_number, 'url' => route('savings-accounts.show', $account)],
            ['label' => 'Deposit'],
        ],
        'actions' => '<a href="' . route('savings-accounts.show', $account) . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Account
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('savings-accounts.do-deposit', $account) }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-6">

            {{-- Deposit Information --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-plus-circle me-2"></i>Deposit Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" value="{{ old('amount') }}" step="0.01" min="0.01" required placeholder="0.00">
                            @error('amount') <span class="text-danger small">{{ $message }}</span> @enderror
                            <div class="form-text">Minimum deposit: {{ number_format(optional($account->savingsProduct)->minimum_amount ?? 0, 2) }}</div>
                        </div>
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
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional notes about this deposit">{{ old('description') }}</textarea>
                            @error('description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('savings-accounts.show', $account) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-plus-circle me-1"></i> Process Deposit
                </button>
            </div>

        </div>
    </div>
</form>
@endsection
