@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Record Loan Payment</h4>
        </div>
    </div>

    @include('layouts.components.alerts')

    <div class="row g-3 g-xl-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Payment Details</h5>
                </div>
                <div class="card-body">
                    {{-- Loan Info --}}
                    <div class="bg-light rounded p-3 mb-4">
                        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3">
                            <div>
                                <small class="text-muted d-block">Loan Number</small>
                                <div class="fw-semibold text-break">{{ $loan->loan_number }}</div>
                            </div>
                            <div>
                                <small class="text-muted d-block">Member</small>
                                <div class="fw-semibold text-break">{{ $loan->member->first_name ?? '' }} {{ $loan->member->last_name ?? '' }}</div>
                            </div>
                            <div>
                                <small class="text-muted d-block">Principal</small>
                                <div class="fw-semibold text-break">TSh {{ number_format($loan->principal_amount, 2) }}</div>
                            </div>
                            <div>
                                <small class="text-muted d-block">Paid</small>
                                <div class="fw-semibold text-success text-break">TSh {{ number_format($loan->amount_paid, 2) }}</div>
                            </div>
                            <div>
                                <small class="text-muted d-block">Outstanding</small>
                                <div class="fw-semibold text-danger text-break">TSh {{ number_format($loan->outstanding_balance, 2) }}</div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('loan-repayments.store', $loan) }}" method="POST">
                        @csrf

                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xxl-4">
                                <label for="amount" class="form-label">Payment Amount (TSh) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('amount') is-invalid @enderror"
                                       id="amount" name="amount" value="{{ old('amount') }}"
                                       min="0.01" max="{{ $loan->outstanding_balance }}" step="0.01" required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Maximum: TSh {{ number_format($loan->outstanding_balance, 2) }}</small>
                            </div>

                            <div class="col-12 col-md-6 col-xxl-4">
                                <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('payment_date') is-invalid @enderror"
                                       id="payment_date" name="payment_date"
                                       value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                                @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6 col-xxl-4">
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

                            <div class="col-12 col-md-6 col-xxl-4">
                                <label for="reference_number" class="form-label">Reference Number</label>
                                <input type="text" class="form-control @error('reference_number') is-invalid @enderror"
                                       id="reference_number" name="reference_number"
                                       value="{{ old('reference_number') }}" maxlength="100">
                                @error('reference_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-xxl-8">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror"
                                          id="notes" name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                            <a href="{{ route('loans.show', $loan) }}" class="btn btn-secondary w-100 w-sm-auto">Cancel</a>
                            <button type="submit" class="btn btn-primary w-100 w-sm-auto">
                                <i class="bi bi-check-circle me-1"></i> Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
