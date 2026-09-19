@extends('layouts.member')

@section('breadcrumb')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('member.guarantor.requests') }}">Guarantor Requests</a></li>
        <li class="breadcrumb-item active">{{ $guarantor->application->application_number ?? 'Request' }}</li>
    </ol>
</nav>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text me-2"></i>Loan Application</h6>
                    @php
                        $statusColors = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger', 'withdrawn' => 'secondary'];
                        $color = $statusColors[$guarantor->status->value] ?? 'secondary';
                    @endphp
                    <span class="badge bg-{{ $color }} fs-6">{{ $guarantor->status->label() }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Application Number</label>
                        <div class="fw-medium">{{ $guarantor->application->application_number ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Loan Plan</label>
                        <div class="fw-medium">{{ $guarantor->application->loanPlan->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Requested Amount</label>
                        <div class="fw-medium">TSh {{ number_format($guarantor->application->requested_amount ?? 0, 0) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Requested Term</label>
                        <div class="fw-medium">{{ $guarantor->application->requested_term ?? '—' }} months</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Loan Purpose</label>
                        <div class="fw-medium">{{ ucfirst($guarantor->application->loan_purpose?->value ?? '—') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Application Status</label>
                        <div>
                            @php
                                $appStatusColors = ['draft' => 'secondary', 'submitted' => 'info', 'under_review' => 'primary', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
                                $appColor = $appStatusColors[$guarantor->application->status->value] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $appColor }}">{{ $guarantor->application->status->label() }}</span>
                        </div>
                    </div>
                    @if($guarantor->application->purpose_description)
                    <div class="col-12">
                        <label class="form-label text-muted small">Purpose Description</label>
                        <div class="fw-medium">{{ $guarantor->application->purpose_description }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Applicant Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Applicant Name</label>
                        <div class="fw-medium">{{ $guarantor->application->member->full_name ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Member Number</label>
                        <div class="fw-medium">{{ $guarantor->application->member->member_number ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield me-2"></i>Guarantor Request Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Your Guaranteed Amount</label>
                        <div class="fw-bold text-primary fs-5">TSh {{ number_format($guarantor->guaranteed_amount, 0) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Request Date</label>
                        <div class="fw-medium">{{ $guarantor->created_at->format('d M Y H:i') }}</div>
                    </div>
                    @if($guarantor->confirmed_at)
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Accepted Date</label>
                        <div class="fw-medium text-success">{{ $guarantor->confirmed_at->format('d M Y H:i') }}</div>
                    </div>
                    @endif
                    @if($guarantor->rejected_at)
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Rejected Date</label>
                        <div class="fw-medium text-danger">{{ $guarantor->rejected_at->format('d M Y H:i') }}</div>
                    </div>
                    @endif
                    @if($guarantor->rejection_reason)
                    <div class="col-12">
                        <label class="form-label text-muted small">Rejection Reason</label>
                        <div class="alert alert-danger mb-0">{{ $guarantor->rejection_reason }}</div>
                    </div>
                    @endif
                    @if($guarantor->notes)
                    <div class="col-12">
                        <label class="form-label text-muted small">Notes</label>
                        <div class="fw-medium">{{ $guarantor->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($guarantor->application->collaterals->count() > 0)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-building me-2"></i>Collateral ({{ $guarantor->application->collaterals->count() }})</h6>
            </div>
            <div class="card-body">
                @foreach($guarantor->application->collaterals as $collateral)
                <div class="d-flex justify-content-between align-items-center {{ !$loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                    <div>
                        <div class="fw-medium">{{ ucfirst(str_replace('_', ' ', $collateral->collateral_type->value)) }}</div>
                        <small class="text-muted">{{ $collateral->description ?? 'No description' }}</small>
                    </div>
                    <div class="text-end">
                        <div class="fw-medium">TSh {{ number_format($collateral->estimated_value ?? 0, 0) }}</div>
                        <small class="text-muted">{{ $collateral->status->label() }}</small>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Other Guarantors ({{ $guarantor->application->guarantors->count() }})</h6>
            </div>
            <div class="card-body">
                @forelse($guarantor->application->guarantors as $g)
                <div class="d-flex justify-content-between align-items-center {{ !$loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                    <div>
                        <div class="fw-medium">
                            {{ $g->guarantorMember->full_name ?? '—' }}
                            @if($g->id === $guarantor->id)
                                <span class="badge bg-primary ms-1">You</span>
                            @endif
                        </div>
                        <small class="text-muted">TSh {{ number_format($g->guaranteed_amount, 0) }}</small>
                    </div>
                    @php
                        $gStatusColors = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger', 'withdrawn' => 'secondary'];
                        $gColor = $gStatusColors[$g->status->value] ?? 'secondary';
                    @endphp
                    <span class="badge bg-{{ $gColor }}">{{ $g->status->label() }}</span>
                </div>
                @empty
                <div class="text-muted text-center py-3">No other guarantors.</div>
                @endforelse
            </div>
        </div>

        @if($guarantor->status->value === 'pending')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2"></i>Actions</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">You have been requested to guarantee this loan application. Please review the details carefully before making a decision.</p>

                <form method="POST" action="{{ route('member.guarantor.accept', $guarantor) }}" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Are you sure you want to accept this guarantor request?')">
                        <i class="bi bi-check-lg me-1"></i> Accept Request
                    </button>
                </form>

                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-lg me-1"></i> Reject Request
                </button>
            </div>
        </div>
        @endif

        <a href="{{ route('member.guarantor.requests') }}" class="btn btn-outline-secondary w-100">
            <i class="bi bi-arrow-left me-1"></i> Back to Requests
        </a>
    </div>
</div>

@if($guarantor->status->value === 'pending')
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('member.guarantor.reject', $guarantor) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Guarantor Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Please provide a reason for rejecting this guarantor request.</p>
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required maxlength="1000" placeholder="Enter reason for rejection..."></textarea>
                        @error('rejection_reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
