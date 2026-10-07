@extends('layouts.app')

@section('title', 'Review Repayment')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Review Repayment',
        'subtitle' => 'Repayment #' . $loanRepayment->repayment_number,
        'breadcrumb' => [
            ['label' => 'Pending Repayments', 'url' => route('loan-repayments.review.index')],
            ['label' => $loanRepayment->repayment_number],
        ],
    ])
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Repayment Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Member</div>
                        <div class="fw-semibold">{{ $loanRepayment->member->full_name }}</div>
                        <div class="text-muted small">{{ $loanRepayment->member->member_number }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Loan</div>
                        <div class="fw-semibold">{{ $loanRepayment->loan->loan_number }}</div>
                        <div class="text-muted small">{{ $loanRepayment->loan->loanPlan->name ?? '' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Amount</div>
                        <div class="fw-semibold">TSh {{ number_format($loanRepayment->amount, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Payment Date</div>
                        <div>{{ $loanRepayment->payment_date?->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Payment Method</div>
                        <div>{{ $loanRepayment->paymentMethod->name ?? $loanRepayment->payment_method }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Reference</div>
                        <div>{{ $loanRepayment->reference_number ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Submitted By</div>
                        <div>{{ $loanRepayment->receiver->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Submitted At</div>
                        <div>{{ $loanRepayment->created_at?->format('d M Y H:i') }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Notes</div>
                        <div>{{ $loanRepayment->notes ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($loanRepayment->loan))
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Loan Status Before Approval</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Outstanding Balance</div>
                            <div class="fw-semibold">TSh {{ number_format($loanRepayment->loan->outstanding_balance, 2) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Amount Paid</div>
                            <div>TSh {{ number_format($loanRepayment->loan->amount_paid, 2) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Status</div>
                            <div><span class="badge bg-{{ $loanRepayment->loan->status->color() ?? 'secondary' }}">{{ $loanRepayment->loan->status->label() ?? $loanRepayment->loan->status }}</span></div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">
                        Upon approval, the loan balance will be reduced by <strong>TSh {{ number_format($loanRepayment->amount, 2) }}</strong>.
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <form method="POST" action="{{ route('loan-repayments.review.approve', $loanRepayment) }}" class="d-grid">
                    @csrf
                    <button type="submit" class="btn btn-success" onclick="return confirm('Approve this repayment and update the loan balance?')">
                        <i class="bi bi-check-circle me-1"></i> Approve & Post
                    </button>
                </form>

                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>

                <a href="{{ route('loan-repayments.review.index') }}" class="btn btn-light">Back to Pending List</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loan-repayments.review.reject', $loanRepayment) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Repayment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" rows="3" class="form-control" required minlength="3">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
