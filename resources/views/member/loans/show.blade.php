@extends('layouts.member')

@section('title', 'Loan Details - ' . $loan->loan_number)
@section('page-title', 'Loan Details')


@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        {{-- Loan Header --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2"></i>Loan {{ $loan->loan_number }}</h6>
                <span class="badge bg-{{ $loan->status->color() }} fs-6">{{ $loan->status->label() }}</span>
            </div>
            <div class="card-body">
                {{-- Loan Progress --}}
                @php
                    $totalDue = (float) $loan->total_amount;
                    $paid = (float) $loan->amount_paid;
                    $progress = $totalDue > 0 ? round(($paid / $totalDue) * 100) : 0;
                @endphp
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Repayment Progress</span>
                        <span class="small fw-semibold">{{ $progress }}%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">Paid: TSh {{ number_format($loan->amount_paid, 0) }}</small>
                        <small class="text-muted">Total: TSh {{ number_format($loan->total_amount, 0) }}</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Loan Plan</small>
                        <div class="fw-semibold">{{ $loan->loanPlan->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Interest Method</small>
                        <div class="fw-semibold">{{ $loan->interest_method->label() }} ({{ $loan->interest_rate }}%)</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Disbursement Date</small>
                        <div class="fw-semibold">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Maturity Date</small>
                        <div class="fw-semibold">{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Next Payment</small>
                        <div class="fw-semibold">{{ $loan->next_payment_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Installments</small>
                        <div class="fw-semibold">{{ $loan->installments_paid }} / {{ $loan->total_installments }} paid</div>
                    </div>
                    @if($loan->loanApplication?->loan_purpose)
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Purpose</small>
                        <div class="fw-semibold">{{ $loan->loanApplication->loan_purpose->label() }}</div>
                    </div>
                    @endif
                    @if($loan->loanApplication?->purpose_description)
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Description</small>
                        <div class="fw-semibold">{{ $loan->loanApplication->purpose_description }}</div>
                    </div>
                    @endif
                </div>

                <hr>

                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Principal</small>
                            <div class="fw-bold fs-5 text-primary">TSh {{ number_format($loan->principal_amount, 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Total Interest</small>
                            <div class="fw-bold fs-5 text-info">TSh {{ number_format($loan->total_interest, 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Amount Paid</small>
                            <div class="fw-bold fs-5 text-success">TSh {{ number_format($loan->amount_paid, 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-danger bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Outstanding</small>
                            <div class="fw-bold fs-5 text-danger">TSh {{ number_format($loan->outstanding_balance, 0) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Repayment Schedule Preview --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2"></i>Repayment Schedule</h6>
                <a href="{{ route('member.loans.schedule', $loan) }}" class="btn btn-outline-primary btn-sm">View Full Schedule</a>
            </div>
            <div class="card-body p-0">
                @if($loan->repaymentSchedule->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        No schedule generated yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th class="text-end">Principal</th>
                                    <th class="text-end">Interest</th>
                                    <th class="text-end">Total Due</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Outstanding</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loan->repaymentSchedule->take(5) as $installment)
                                    <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : ($installment->status->value === 'paid' ? 'table-success' : '') }}">
                                        <td class="fw-medium">{{ $installment->installment_number }}</td>
                                        <td>{{ $installment->due_date->format('d M Y') }}</td>
                                        <td class="text-end">TSh {{ number_format($installment->principal_amount, 0) }}</td>
                                        <td class="text-end">TSh {{ number_format($installment->interest_amount, 0) }}</td>
                                        <td class="text-end fw-medium">TSh {{ number_format($installment->total_amount, 0) }}</td>
                                        <td class="text-end text-success">TSh {{ number_format($installment->amount_paid, 0) }}</td>
                                        <td class="text-end text-danger">TSh {{ number_format($installment->outstanding_amount, 0) }}</td>
                                        <td><span class="badge bg-{{ $installment->status->color() }}">{{ $installment->status->label() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Repayments --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Payments</h6>
            </div>
            <div class="card-body p-0">
                @if($loan->repayments->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No payments recorded yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Repayment #</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loan->repayments as $repayment)
                                    <tr>
                                        <td>
                                            <a href="{{ route('member.repayments.show', $repayment) }}" class="fw-semibold text-decoration-none">
                                                {{ $repayment->repayment_number }}
                                            </a>
                                        </td>
                                        <td>{{ $repayment->payment_date->format('d M Y') }}</td>
                                        <td class="text-end fw-semibold">TSh {{ number_format($repayment->amount, 2) }}</td>
                                        <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $repayment->payment_method)) }}</span></td>
                                        <td>
                                            <span class="badge bg-{{ $repayment->status->color() }}">
                                                {{ $repayment->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Actions --}}
        @if(in_array($loan->status->value, ['active']) && (float) $loan->outstanding_balance > 0)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <a href="{{ route('member.loans.repay', $loan) }}" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-credit-card me-1"></i> Make a Payment
                </a>
                <a href="{{ route('member.loans.schedule', $loan) }}" class="btn btn-outline-info w-100 mb-2">
                    <i class="bi bi-calendar3 me-1"></i> View Schedule
                </a>
                <a href="{{ route('member.loans.statement', $loan) }}" class="btn btn-outline-success w-100 mb-2">
                    <i class="bi bi-file-text me-1"></i> View Statement
                </a>
                <a href="{{ route('member.loans') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left me-1"></i> Back to Loans
                </a>
            </div>
        </div>
        @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <a href="{{ route('member.loans.schedule', $loan) }}" class="btn btn-outline-info w-100 mb-2">
                    <i class="bi bi-calendar3 me-1"></i> View Schedule
                </a>
                <a href="{{ route('member.loans.statement', $loan) }}" class="btn btn-outline-success w-100 mb-2">
                    <i class="bi bi-file-text me-1"></i> View Statement
                </a>
                <a href="{{ route('member.loans') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left me-1"></i> Back to Loans
                </a>
            </div>
        </div>
        @endif

        {{-- Loan Summary --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">Loan Summary</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Principal Amount</span>
                    <span class="fw-semibold">TSh {{ number_format($loan->principal_amount, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Interest</span>
                    <span class="fw-semibold">TSh {{ number_format($loan->total_interest, 2) }}</span>
                </div>
                @if((float) $loan->processing_fee > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span>Processing Fee</span>
                    <span class="fw-semibold">TSh {{ number_format($loan->processing_fee, 2) }}</span>
                </div>
                @endif
                @if((float) $loan->insurance_fee > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span>Insurance Fee</span>
                    <span class="fw-semibold">TSh {{ number_format($loan->insurance_fee, 2) }}</span>
                </div>
                @endif
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Amount</span>
                    <span class="fw-semibold">TSh {{ number_format($loan->total_amount, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Amount Paid</span>
                    <span class="fw-semibold text-success">TSh {{ number_format($loan->amount_paid, 2) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Outstanding Balance</span>
                    <span class="fw-bold text-danger">TSh {{ number_format($loan->outstanding_balance, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Loan Progress Card --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">Loan Progress</h6>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="display-4 fw-bold text-{{ $progress >= 100 ? 'success' : 'primary' }}">{{ $progress }}%</div>
                    <small class="text-muted">Repayment Complete</small>
                </div>
                <div class="progress mb-3" style="height: 12px;">
                    <div class="progress-bar bg-{{ $progress >= 100 ? 'success' : 'primary' }}" style="width: {{ $progress }}%"></div>
                </div>
                <div class="row text-center small">
                    <div class="col-6">
                        <div class="text-muted">Installments Paid</div>
                        <div class="fw-semibold">{{ $loan->installments_paid }} / {{ $loan->total_installments }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Amount Paid</div>
                        <div class="fw-semibold text-success">TSh {{ number_format($loan->amount_paid, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
