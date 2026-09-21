@extends('layouts.member')

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
                        $statusLabel = $guarantor->status->label();
                        if ($guarantor->status->value === 'pending' && $guarantor->confirmed_at) {
                            $statusLabel = 'Awaiting Review';
                            $color = 'info';
                        }
                    @endphp
                    <span class="badge bg-{{ $color }} fs-6">{{ $statusLabel }}</span>
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
                        <label class="form-label text-muted small">Term</label>
                        <div class="fw-medium">{{ $guarantor->application->requested_term ?? '—' }} months</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Purpose</label>
                        <div class="fw-medium">{{ $guarantor->application->loan_purpose?->label() ?? '—' }}</div>
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
                        <label class="form-label text-muted small">Description</label>
                        <div class="fw-medium">{{ $guarantor->application->purpose_description }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Applicant</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Name</label>
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
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield me-2"></i>Guarantor Request</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Guaranteed Amount</label>
                        <div class="fw-bold text-primary fs-5">TSh {{ number_format($guarantor->guaranteed_amount, 0) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Request Date</label>
                        <div class="fw-medium">{{ $guarantor->created_at->format('d M Y H:i') }}</div>
                    </div>
                    @if($guarantor->confirmed_at)
                    <div class="col-md-6">
                        <label class="form-label text-muted small">{{ $guarantor->status->value === 'accepted' ? 'Approved Date' : 'Accepted by You' }}</label>
                        <div class="fw-medium text-{{ $guarantor->status->value === 'accepted' ? 'success' : 'info' }}">{{ $guarantor->confirmed_at->format('d M Y H:i') }}</div>
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
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>All Guarantors ({{ $guarantor->application->guarantors->count() }})</h6>
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
                        @if($g->guarantorMember?->national_id)
                            <br><small class="text-muted font-monospace">NIDA: {{ $g->guarantorMember->national_id }}</small>
                        @endif
                    </div>
                    @php
                        $gStatusColors = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger', 'withdrawn' => 'secondary'];
                        $gColor = $gStatusColors[$g->status->value] ?? 'secondary';
                    @endphp
                    <span class="badge bg-{{ $gColor }}">{{ $g->status->label() }}</span>
                </div>
                @empty
                <div class="text-muted text-center py-3">No guarantors.</div>
                @endforelse
            </div>
        </div>

        @if($guarantor->status->value === 'pending' && !$guarantor->confirmed_at)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2"></i>Actions</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">You have been requested to guarantee this loan. Please review the details carefully.</p>

                @php
                    $hasActiveGuarantee = $member && \App\Models\LoanApplicationGuarantor::hasActiveGuarantee($member->id);
                @endphp

                @if($hasActiveGuarantee)
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div class="small">
                        <strong>Blocked:</strong> You currently have an active guaranteed loan that has not been fully repaid.
                    </div>
                </div>
                @endif

                @error('error')
                <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div class="small">{{ $message }}</div>
                </div>
                @enderror

                <div class="alert alert-light border mb-3">
                    <h6 class="text-muted mb-2">Your Details (from profile)</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted d-block">Name</small>
                            <strong>{{ $member->full_name }}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Member #</small>
                            <strong>{{ $member->member_number }}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">NIDA</small>
                            <strong class="font-monospace">{{ $member->national_id ?? '—' }}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Phone</small>
                            <strong>{{ $member->phone ?? '—' }}</strong>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('member.guarantor.accept', $guarantor) }}" class="mb-2">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Guaranteed Amount (TSh) <span class="text-danger">*</span></label>
                        <input type="number" name="guaranteed_amount" class="form-control" step="1" min="1" required
                               value="{{ old('guaranteed_amount', $guarantor->guaranteed_amount) }}"
                               max="{{ $guarantor->application->requested_amount }}"
                               {{ $hasActiveGuarantee ? 'disabled' : '' }}>
                        <div class="form-text">Max: TSh {{ number_format($guarantor->application->requested_amount ?? 0, 0) }}</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100" {{ $hasActiveGuarantee ? 'disabled' : '' }}
                            onclick="return confirm('Accept this guarantor request?')">
                        <i class="bi bi-check-lg me-1"></i> Accept
                    </button>
                </form>

                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-lg me-1"></i> Reject
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
                    <p class="text-muted">Please provide a reason for rejecting this request.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required maxlength="1000" placeholder="Enter reason..."></textarea>
                        @error('rejection_reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
