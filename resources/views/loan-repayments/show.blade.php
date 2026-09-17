@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Payment Details</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loanRepayment->loan) }}">{{ $loanRepayment->loan->loan_number }}</a></li>
                    <li class="breadcrumb-item active">{{ $loanRepayment->repayment_number }}</li>
                </ol>
            </nav>
        </div>
        <div>
            @if($loanRepayment->status->isReversible())
            @can('reverse', $loanRepayment)
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#reverseModal">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reverse Payment
            </button>
            @endcan
            @endif
        </div>
    </div>

    @include('layouts.components.alerts')

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $loanRepayment->repayment_number }}</h5>
                    <span class="badge bg-{{ $loanRepayment->status->color() }} fs-6">{{ $loanRepayment->status->label() }}</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">Loan Number</small>
                            <a href="{{ route('loans.show', $loanRepayment->loan) }}" class="fw-semibold text-decoration-none">
                                {{ $loanRepayment->loan->loan_number }}
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">Member</small>
                            <div class="fw-semibold">{{ $loanRepayment->loan->member->first_name ?? '' }} {{ $loanRepayment->loan->member->last_name ?? '' }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">Payment Date</small>
                            <div class="fw-semibold">{{ $loanRepayment->payment_date->format('d M Y') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">Payment Method</small>
                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $loanRepayment->payment_method)) }}</div>
                        </div>
                        @if($loanRepayment->reference_number)
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">Reference Number</small>
                            <div class="fw-semibold">{{ $loanRepayment->reference_number }}</div>
                        </div>
                        @endif
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">Received By</small>
                            <div class="fw-semibold">{{ $loanRepayment->receiver->fullname ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <hr>

                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <small class="text-muted d-block">Total Amount</small>
                                <div class="fw-bold fs-5 text-success">TSh {{ number_format($loanRepayment->amount, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <small class="text-muted d-block">Principal</small>
                                <div class="fw-bold fs-5 text-primary">TSh {{ number_format($loanRepayment->principal_portion, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-info bg-opacity-10 rounded p-3">
                                <small class="text-muted d-block">Interest</small>
                                <div class="fw-bold fs-5 text-info">TSh {{ number_format($loanRepayment->interest_portion, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-warning bg-opacity-10 rounded p-3">
                                <small class="text-muted d-block">Fees</small>
                                <div class="fw-bold fs-5 text-warning">TSh {{ number_format($loanRepayment->fee_portion, 2) }}</div>
                            </div>
                        </div>
                    </div>

                    @if($loanRepayment->notes)
                    <hr>
                    <div>
                        <small class="text-muted d-block">Notes</small>
                        <div>{{ $loanRepayment->notes }}</div>
                    </div>
                    @endif

                    @if($loanRepayment->reversal_reason)
                    <hr>
                    <div class="alert alert-danger mb-0">
                        <small class="d-block fw-semibold">Reversal Reason</small>
                        {{ $loanRepayment->reversal_reason }}
                    </div>
                    @endif
                </div>
            </div>

            {{-- Allocations --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Allocations</h5>
                </div>
                <div class="card-body p-0">
                    @if($loanRepayment->allocations->isEmpty())
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
                                    @foreach($loanRepayment->allocations as $allocation)
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
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Loan Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Principal Amount</span>
                        <span class="fw-semibold">TSh {{ number_format($loanRepayment->loan->principal_amount, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Interest</span>
                        <span class="fw-semibold">TSh {{ number_format($loanRepayment->loan->total_interest, 2) }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Amount</span>
                        <span class="fw-semibold">TSh {{ number_format($loanRepayment->loan->total_amount, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Amount Paid</span>
                        <span class="fw-semibold text-success">TSh {{ number_format($loanRepayment->loan->amount_paid, 2) }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Outstanding Balance</span>
                        <span class="fw-bold text-danger">TSh {{ number_format($loanRepayment->loan->outstanding_balance, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <a href="{{ route('loan-repayments.index', $loanRepayment->loan) }}" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-list-ul me-1"></i> View All Repayments
                    </a>
                    <a href="{{ route('loans.statement', $loanRepayment->loan) }}" class="btn btn-outline-info w-100 mb-2">
                        <i class="bi bi-file-earmark-text me-1"></i> Loan Statement
                    </a>
                    <a href="{{ route('loans.show', $loanRepayment->loan) }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-1"></i> Back to Loan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reverse Modal --}}
@if($loanRepayment->status->isReversible())
@can('reverse', $loanRepayment)
<div class="modal fade" id="reverseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('loan-repayments.reverse', $loanRepayment) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reverse Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Are you sure you want to reverse this payment of <strong>TSh {{ number_format($loanRepayment->amount, 2) }}</strong>?
                        This action cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required minlength="10" maxlength="500"
                                  placeholder="Please provide a detailed reason for the reversal..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Confirm Reversal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endif
@endsection
