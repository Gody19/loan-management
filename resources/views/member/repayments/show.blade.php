@extends('layouts.member')

@section('title', 'Payment Details - ' . $repayment->repayment_number)
@section('page-title', 'Payment Details')


@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">{{ $repayment->repayment_number }}</h5>
                <span class="badge bg-{{ $repayment->status->color() }} fs-6">{{ $repayment->status->label() }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Loan Number</small>
                        <a href="{{ route('member.loans.show', $repayment->loan) }}" class="fw-semibold text-decoration-none">
                            {{ $repayment->loan->loan_number }}
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Payment Date</small>
                        <div class="fw-semibold">{{ $repayment->payment_date->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Payment Method</small>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $repayment->payment_method)) }}</div>
                    </div>
                    @if($repayment->reference_number)
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Reference Number</small>
                        <div class="fw-semibold">{{ $repayment->reference_number }}</div>
                    </div>
                    @endif
                </div>

                <hr>

                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Total Amount</small>
                            <div class="fw-bold fs-5 text-success">TSh {{ number_format($repayment->amount, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Principal</small>
                            <div class="fw-bold fs-5 text-primary">TSh {{ number_format($repayment->principal_portion, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Interest</small>
                            <div class="fw-bold fs-5 text-info">TSh {{ number_format($repayment->interest_portion, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <small class="text-muted d-block">Fees</small>
                            <div class="fw-bold fs-5 text-warning">TSh {{ number_format($repayment->fee_portion, 2) }}</div>
                        </div>
                    </div>
                </div>

                @if($repayment->notes)
                <hr>
                <div>
                    <small class="text-muted d-block">Notes</small>
                    <div>{{ $repayment->notes }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Allocations --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold">Payment Allocations</h5>
            </div>
            <div class="card-body p-0">
                @if($repayment->allocations->isEmpty())
                    <div class="text-center py-4">
                        <p class="text-muted mb-0">No allocations recorded.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Installment #</th>
                                    <th>Due Date</th>
                                    <th class="text-end">Allocated</th>
                                    <th class="text-end">Principal</th>
                                    <th class="text-end">Interest</th>
                                    <th class="text-end">Fees</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($repayment->allocations as $allocation)
                                <tr>
                                    <td>Installment {{ $allocation->installment->installment_number ?? 'N/A' }}</td>
                                    <td>{{ $allocation->installment->due_date?->format('d M Y') ?? 'N/A' }}</td>
                                    <td class="text-end fw-semibold">TSh {{ number_format($allocation->amount, 2) }}</td>
                                    <td class="text-end">TSh {{ number_format($allocation->principal_allocation, 2) }}</td>
                                    <td class="text-end">TSh {{ number_format($allocation->interest_allocation, 2) }}</td>
                                    <td class="text-end">TSh {{ number_format($allocation->fee_allocation, 2) }}</td>
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
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold">Loan Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Principal Amount</span>
                    <span class="fw-semibold">TSh {{ number_format($repayment->loan->principal_amount, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Interest</span>
                    <span class="fw-semibold">TSh {{ number_format($repayment->loan->total_interest, 2) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Amount</span>
                    <span class="fw-semibold">TSh {{ number_format($repayment->loan->total_amount, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Amount Paid</span>
                    <span class="fw-semibold text-success">TSh {{ number_format($repayment->loan->amount_paid, 2) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Outstanding Balance</span>
                    <span class="fw-bold text-danger">TSh {{ number_format($repayment->loan->outstanding_balance, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <a href="{{ route('member.repayments') }}" class="btn btn-outline-primary w-100 mb-2">
                    <i class="bi bi-list-ul me-1"></i> View All Repayments
                </a>
                <a href="{{ route('member.loans.show', $repayment->loan) }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left me-1"></i> Back to Loan
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
