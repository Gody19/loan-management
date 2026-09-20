@extends('layouts.app')

@section('title', 'Loan Application ' . $application->application_number)

@section('page-header')
    @php
        $csrf = '<input type="hidden" name="_token" value="' . csrf_token() . '">';
        $actions = '<a href="' . route('loan-applications.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>';
        if ($application->status->value === 'draft') {
            $actions .= '<a href="' . route('loan-applications.edit', $application) . '" class="btn btn-warning"><i class="bi bi-pencil me-1"></i> Edit</a>';
            $actions .= '<form method="POST" action="' . route('loan-applications.submit', $application) . '" class="d-inline" data-confirm="Submit this application?">' . $csrf . '<button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit</button></form>';
        }
        if ($application->status->value === 'submitted') {
            $actions .= '<form method="POST" action="' . route('loan-applications.review', $application) . '" class="d-inline" data-confirm="Move to review?">' . $csrf . '<button type="submit" class="btn btn-info text-white"><i class="bi bi-search me-1"></i> Review</button></form>';
        }
        if ($application->status->value === 'under_review') {
            $actions .= '<form method="POST" action="' . route('loan-applications.approve', $application) . '" class="d-inline" data-confirm="Approve this application?">' . $csrf . '<button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Approve</button></form>';
            $actions .= '<form method="POST" action="' . route('loan-applications.reject', $application) . '" class="d-inline">' . $csrf . '<div class="input-group input-group-sm" style="max-width:300px"><input type="text" name="rejection_reason" class="form-control" placeholder="Rejection reason..." required><button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i></button></div></form>';
        }
        if (in_array($application->status->value, ['draft', 'submitted'])) {
            $actions .= '<form method="POST" action="' . route('loan-applications.cancel', $application) . '" class="d-inline" data-confirm="Cancel this application?">' . $csrf . '<input type="hidden" name="cancellation_reason" value="Cancelled by user"><button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i> Cancel</button></form>';
        }
        if ($application->status->value === 'approved') {
            $actions .= '<form method="POST" action="' . route('loans.create-from-application', $application) . '" class="d-inline" data-confirm="Create a loan from this approved application?">' . $csrf . '<button type="submit" class="btn btn-success"><i class="bi bi-cash-coin me-1"></i> Create Loan</button></form>';
        }
    @endphp
    @include('layouts.components.page-header', [
        'title' => 'Application ' . $application->application_number,
        'subtitle' => $application->member->full_name ?? '—' . ' | ' . $application->status->label(),
        'breadcrumb' => [
            ['label' => 'Loan Applications', 'url' => route('loan-applications.index')],
            ['label' => $application->application_number],
        ],
        'actions' => $actions,
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Summary Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-cash-coin text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $application->application_number }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-{{ $application->status->color() }}">{{ $application->status->label() }}</span>
                            <span class="text-muted">{{ $application->loanPlan->name ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Requested Amount</div>
                        <div class="fw-bold fs-4 text-primary">{{ number_format($application->requested_amount, 0) }} TZS</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- Applicant & Loan Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Applicant & Loan</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Member</td><td class="fw-medium">{{ $application->member->full_name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Member #</td><td class="fw-medium">{{ $application->member->member_number ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Loan Plan</td><td class="fw-medium">{{ $application->loanPlan->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Branch</td><td class="fw-medium">{{ $application->branch->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">VICOBA Group</td><td class="fw-medium">{{ $application->vicobaGroup->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Term</td><td class="fw-medium">{{ $application->requested_term }} months</td></tr>
                            <tr><td class="text-muted">Frequency</td><td class="fw-medium">{{ $application->repayment_frequency->label() }}</td></tr>
                            <tr><td class="text-muted">Purpose</td><td class="fw-medium">{{ $application->loan_purpose->label() }}</td></tr>
                            <tr><td class="text-muted">Description</td><td class="fw-medium">{{ $application->purpose_description ?? '—' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Eligibility Snapshot --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clipboard-check me-2"></i>Eligibility Snapshot</h6>
                    </div>
                    <div class="card-body">
                        @php $snap = $application->eligibility_snapshot; @endphp
                        @if($snap)
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Eligible</td><td><span class="badge bg-{{ ($snap['eligible'] ?? false) ? 'success' : 'danger' }}">{{ ($snap['eligible'] ?? false) ? 'Yes' : 'No' }}</span></td></tr>
                            <tr><td class="text-muted">Requested</td><td class="fw-medium">{{ number_format($snap['requested_amount'] ?? 0, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Approved Amount</td><td class="fw-medium">{{ number_format($snap['approved_amount'] ?? 0, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Failure Reasons</td><td class="fw-medium">{{ implode(', ', $snap['failure_reasons'] ?? []) ?: '—' }}</td></tr>
                        </table>
                        @else
                            <div class="text-muted text-center py-3">No eligibility snapshot available.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Guarantors --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Guarantors ({{ $application->guarantors->count() }})</h6>
                        @if(in_array($application->status->value, ['draft', 'submitted']))
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addGuarantorModal">
                            <i class="bi bi-plus"></i>
                        </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @forelse($application->guarantors as $g)
                            <div class="d-flex align-items-center justify-content-between mb-2 {{ !$loop->last ? 'border-bottom pb-2' : '' }}">
                                <div>
                                    <div class="fw-medium">{{ $g->guarantorMember->full_name ?? '—' }}</div>
                                    <small class="text-muted">{{ number_format($g->guaranteed_amount, 0) }} TZS</small>
                                </div>
                                <span class="badge bg-{{ $g->status->value === 'accepted' ? 'success' : ($g->status->value === 'rejected' ? 'danger' : 'warning') }}">{{ $g->status->value }}</span>
                            </div>
                        @empty
                            <div class="text-muted text-center py-3">No guarantors added.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Collateral --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-building me-2"></i>Collateral ({{ $application->collaterals->count() }})</h6>
                        @if(in_array($application->status->value, ['draft', 'submitted']))
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCollateralModal">
                            <i class="bi bi-plus"></i>
                        </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @forelse($application->collaterals as $c)
                            <div class="d-flex align-items-center justify-content-between mb-2 {{ !$loop->last ? 'border-bottom pb-2' : '' }}">
                                <div>
                                    <div class="fw-medium">{{ $c->collateral_type->label() }}</div>
                                    <small class="text-muted">{{ Str::limit($c->description, 40) }} | {{ number_format($c->estimated_value, 0) }} TZS</small>
                                </div>
                                <span class="badge bg-{{ $c->status->value === 'verified' ? 'success' : ($c->status->value === 'rejected' ? 'danger' : 'warning') }}">{{ $c->status->value }}</span>
                            </div>
                        @empty
                            <div class="text-muted text-center py-3">No collateral added.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Approval History --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Approval History</h6>
                    </div>
                    <div class="card-body">
                        @forelse($application->approvals as $a)
                            <div class="d-flex align-items-start mb-3 {{ !$loop->last ? 'border-bottom pb-3' : '' }}">
                                <div class="bg-{{ $a->action->value === 'approved' ? 'success' : ($a->action->value === 'rejected' ? 'danger' : 'secondary') }} rounded-circle d-flex align-items-center justify-content-center me-3" style="width:36px;height:36px;">
                                    <i class="bi bi-{{ $a->action->value === 'approved' ? 'check-lg' : ($a->action->value === 'rejected' ? 'x-lg' : 'clock') }} text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-medium">Level {{ $a->approval_level }} — {{ ucfirst($a->action->value) }}</div>
                                    <small class="text-muted">{{ $a->acted_at?->format('d M Y H:i') ?? '—' }} by {{ $a->actor->fullname ?? '—' }}</small>
                                    @if($a->comments)<div class="small text-muted mt-1">{{ $a->comments }}</div>@endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-3">No approval records yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Audit Info --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-2"></i>Audit Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4"><span class="text-muted">Created:</span> {{ $application->created_at?->format('d M Y H:i') ?? '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">Submitted:</span> {{ $application->submitted_at?->format('d M Y H:i') ?? '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">Application Date:</span> {{ $application->application_date }}</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Add Guarantor Modal --}}
@if(in_array($application->status->value, ['draft', 'submitted']))
<div class="modal fade" id="addGuarantorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loan-applications.guarantors.store', $application) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Guarantor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Guarantor Member <span class="text-danger">*</span></label>
                        <select name="guarantor_member_id" class="form-select" required>
                            <option value="">Select Member</option>
                            @foreach(\App\Models\Member::where('organization_id', $application->organization_id)->active()->get() as $m)
                                <option value="{{ $m->id }}">{{ $m->full_name }} ({{ $m->member_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Guaranteed Amount (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="guaranteed_amount" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Guarantor</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Add Collateral Modal --}}
<div class="modal fade" id="addCollateralModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loan-applications.collaterals.store', $application) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Collateral</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Collateral Type <span class="text-danger">*</span></label>
                        <select name="collateral_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="land">Land</option>
                            <option value="vehicle">Vehicle</option>
                            <option value="equipment">Equipment</option>
                            <option value="building">Building</option>
                            <option value="jewelry">Jewelry</option>
                            <option value="savings">Savings</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estimated Value (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="estimated_value" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ownership Details</label>
                        <textarea name="ownership_details" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Collateral</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
