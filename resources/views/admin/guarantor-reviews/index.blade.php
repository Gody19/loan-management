@extends('layouts.app')

@section('title', 'Guarantor Reviews')
@section('page-title', 'Guarantor Review Queue')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        @if($pendingGuarantors->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-shield-check text-muted" style="font-size: 2rem;"></i>
                </div>
                <h5 class="text-muted">No Pending Reviews</h5>
                <p class="text-muted mb-0">There are currently no guarantor requests awaiting review.</p>
            </div>
        </div>
        @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Pending Reviews ({{ $pendingGuarantors->count() }})</h6>
            </div>
            <div class="card-body p-0">
                @foreach($pendingGuarantors as $g)
                <div class="border-bottom p-4 {{ $loop->last ? 'border-bottom-0' : '' }}">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-semibold mb-3"><i class="bi bi-file-text me-1"></i>Loan Application</h6>
                            <table class="table table-borderless table-sm mb-0">
                                <tr><td class="text-muted" style="width:45%">Application #</td><td class="fw-medium">{{ $g->application->application_number ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Applicant</td><td class="fw-medium">{{ $g->application->member->full_name ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Member Number</td><td class="fw-medium">{{ $g->application->member->member_number ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Loan Plan</td><td class="fw-medium">{{ $g->application->loanPlan->name ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Amount</td><td class="fw-medium">TSh {{ number_format($g->application->requested_amount ?? 0, 0) }}</td></tr>
                                <tr><td class="text-muted">Purpose</td><td class="fw-medium">{{ $g->application->loan_purpose?->label() ?? '—' }}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-semibold mb-3"><i class="bi bi-person me-1"></i>Guarantor</h6>
                            <table class="table table-borderless table-sm mb-0">
                                <tr><td class="text-muted" style="width:45%">Name</td><td class="fw-medium">{{ $g->guarantorMember->full_name ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Member Number</td><td class="fw-medium">{{ $g->guarantorMember->member_number ?? '—' }}</td></tr>
                                <tr><td class="text-muted">NIDA</td><td class="fw-medium font-monospace">{{ $g->guarantorMember->national_id ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Phone</td><td class="fw-medium">{{ $g->guarantorMember->phone ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Group</td><td class="fw-medium">{{ $g->guarantorMember->vicobaGroup->name ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Guaranteed Amount</td><td class="fw-bold text-primary">TSh {{ number_format($g->guaranteed_amount, 0) }}</td></tr>
                            </table>

                            @if($g->guarantor_member_id)
                            @php
                                $eligibility = app(\App\Services\GuarantorEligibilityService::class)->getEligibility($g->guarantorMember, $g->application);
                            @endphp
                            <div class="mt-3">
                                <h6 class="fw-semibold mb-2"><i class="bi bi-shield me-1"></i>Eligibility</h6>
                                <span class="badge bg-{{ $eligibility['eligible'] ? 'success' : 'danger' }}">
                                    {{ $eligibility['eligible'] ? 'Eligible' : 'Not Eligible' }}
                                </span>
                                @if($eligibility['reason'])
                                    <div class="text-danger small mt-1">{{ $eligibility['reason'] }}</div>
                                @endif
                                @if($eligibility['active_count'] > 0)
                                    <div class="text-muted small mt-1">Active guarantees: {{ $eligibility['active_count'] }}</div>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <form method="POST" action="{{ route('loan-guarantors.approve', $g) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this guarantor?')">
                                <i class="bi bi-check-lg me-1"></i> Approve
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal-{{ $g->id }}">
                            <i class="bi bi-x-lg me-1"></i> Reject
                        </button>
                    </div>

                    <div class="modal fade" id="rejectModal-{{ $g->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('loan-guarantors.reject', $g) }}">
                                @csrf
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reject Guarantor</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted">Guarantor: <strong>{{ $g->guarantorMember->full_name ?? '—' }}</strong></p>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                                            <textarea name="rejection_reason" class="form-control" rows="3" required maxlength="1000"
                                                      placeholder="Enter reason for rejection..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Reject Guarantor</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
