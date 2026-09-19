@extends('layouts.member')

@section('title', 'Make Payment - ' . $loan->loan_number)
@section('page-title', 'Make a Payment')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('member.loans') }}">My Loans</a></li>
            <li class="breadcrumb-item"><a href="{{ route('member.loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
            <li class="breadcrumb-item active">Make Payment</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold">Payment Details</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="bg-light rounded p-3">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Loan Number</small>
                                    <div class="fw-semibold">{{ $loan->loan_number }}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Loan Plan</small>
                                    <div class="fw-semibold">{{ $loan->loanPlan->name ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light rounded p-3">
                            <div class="row">
                                <div class="col-4">
                                    <small class="text-muted">Principal</small>
                                    <div class="fw-semibold">TSh {{ number_format($loan->principal_amount, 0) }}</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Paid</small>
                                    <div class="fw-semibold text-success">TSh {{ number_format($loan->amount_paid, 0) }}</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Outstanding</small>
                                    <div class="fw-semibold text-danger">TSh {{ number_format($loan->outstanding_balance, 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('member.loans.repay.store', $loan) }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Payment Amount (TSh) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('amount') is-invalid @enderror"
                                   id="amount" name="amount" value="{{ old('amount') }}"
                                   min="0.01" max="{{ $loan->outstanding_balance }}" step="0.01" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Maximum: TSh {{ number_format($loan->outstanding_balance, 2) }}</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('payment_date') is-invalid @enderror"
                                   id="payment_date" name="payment_date"
                                   value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                            @error('payment_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select @error('payment_method') is-invalid @enderror"
                                    id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="mobile_money" {{ old('payment_method') === 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                                <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="check" {{ old('payment_method') === 'check' ? 'selected' : '' }}>Check</option>
                            </select>
                            @error('payment_method')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="payment_method_id" class="form-label">Payment Method (Optional)</label>
                            <select class="form-select @error('payment_method_id') is-invalid @enderror"
                                    id="payment_method_id" name="payment_method_id">
                                <option value="">Select (optional)</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}" {{ old('payment_method_id') == $method->id ? 'selected' : '' }}>
                                        {{ $method->name }} ({{ $method->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_method_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control @error('reference_number') is-invalid @enderror"
                                   id="reference_number" name="reference_number"
                                   value="{{ old('reference_number') }}" maxlength="100">
                            @error('reference_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes" name="notes" rows="1" maxlength="1000">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('member.loans.show', $loan) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary no-spinner">
                            <i class="bi bi-check-circle me-1"></i> Submit Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
