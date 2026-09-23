@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">Record Loan Payment</h4>
    </div>
    <a href="{{ route('loan-repayments-collection.member-loans', $loan->member_id) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

@include('layouts.components.alerts')

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Payment Details</h5>
    </div>
    <div class="card-body">
        {{-- Loan Info --}}
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="bg-light rounded p-3">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Loan Number</small>
                            <div class="fw-semibold">{{ $loan->loan_number }}</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Member</small>
                            <div class="fw-semibold">{{ $loan->member->full_name ?? '' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-light rounded p-3">
                    <div class="row">
                        <div class="col-4">
                            <small class="text-muted">Principal</small>
                            <div class="fw-semibold">TSh {{ number_format($loan->principal_amount, 2) }}</div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Paid</small>
                            <div class="fw-semibold text-success">TSh {{ number_format($loan->amount_paid, 2) }}</div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Outstanding</small>
                            <div class="fw-semibold text-danger">TSh {{ number_format($loan->outstanding_balance, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Installments --}}
        @if($loan->repaymentSchedule->isNotEmpty())
            <div class="mb-4">
                <h6 class="mb-2">Pending Installments</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Due Date</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->repaymentSchedule as $installment)
                                <tr>
                                    <td>{{ $installment->installment_number }}</td>
                                    <td>{{ $installment->due_date->format('d M Y') }}</td>
                                    <td class="text-end">TSh {{ number_format($installment->total_amount, 2) }}</td>
                                    <td class="text-end">TSh {{ number_format($installment->amount_paid, 2) }}</td>
                                    <td class="text-end">TSh {{ number_format($installment->total_amount - $installment->amount_paid, 2) }}</td>
                                    <td>
                                        @if($installment->status->value === 'overdue')
                                            <span class="badge bg-danger">Overdue</span>
                                        @elseif($installment->status->value === 'partial')
                                            <span class="badge bg-warning text-dark">Partial</span>
                                        @else
                                            <span class="badge bg-secondary">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <form action="{{ route('loan-repayments-collection.store-repayment', $loan) }}" method="POST">
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
                    <label for="payment_method_id" class="form-label">Payment Method (Organization)</label>
                    <select class="form-select @error('payment_method_id') is-invalid @enderror"
                            id="payment_method_id" name="payment_method_id">
                        <option value="">None</option>
                        @foreach($paymentMethods as $pm)
                            <option value="{{ $pm->id }}" {{ old('payment_method_id') == $pm->id ? 'selected' : '' }}>
                                {{ $pm->name }} ({{ strtoupper($pm->code) }})
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
                    <label for="idempotency_key" class="form-label">Idempotency Key</label>
                    <input type="text" class="form-control @error('idempotency_key') is-invalid @enderror"
                           id="idempotency_key" name="idempotency_key"
                           value="{{ old('idempotency_key') }}" maxlength="100">
                    @error('idempotency_key')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Optional. Prevents duplicate payments if submitted twice.</small>
                </div>
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea class="form-control @error('notes') is-invalid @enderror"
                          id="notes" name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('loan-repayments-collection.member-loans', $loan->member_id) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
