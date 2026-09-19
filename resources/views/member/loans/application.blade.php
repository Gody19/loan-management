@extends('layouts.member')

@section('title', 'Application ' . $loanApplication->application_number . ' - FinancePro VICOBA')
@section('page-title', 'Application ' . $loanApplication->application_number)

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('member.loans') }}">My Loans</a></li>
            <li class="breadcrumb-item"><a href="{{ route('member.loans.applications') }}">Applications</a></li>
            <li class="breadcrumb-item active">{{ $loanApplication->application_number }}</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-file-text text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $loanApplication->application_number }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-{{ $loanApplication->status->color() }}">{{ $loanApplication->status->label() }}</span>
                            <span class="text-muted">{{ $loanApplication->loanPlan->name ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Requested Amount</div>
                        <div class="fw-bold fs-4 text-primary">TSh {{ number_format($loanApplication->requested_amount, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Application Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Loan Plan</td><td class="fw-medium">{{ $loanApplication->loanPlan->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Requested Amount</td><td class="fw-medium">TSh {{ number_format($loanApplication->requested_amount, 0) }}</td></tr>
                            <tr><td class="text-muted">Term</td><td class="fw-medium">{{ $loanApplication->requested_term }} months</td></tr>
                            <tr><td class="text-muted">Frequency</td><td class="fw-medium">{{ $loanApplication->repayment_frequency->label() }}</td></tr>
                            <tr><td class="text-muted">Purpose</td><td class="fw-medium">{{ $loanApplication->loan_purpose->label() }}</td></tr>
                            <tr><td class="text-muted">Description</td><td class="fw-medium">{{ $loanApplication->purpose_description ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Application Date</td><td class="fw-medium">{{ $loanApplication->application_date?->format('d M Y') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Submitted</td><td class="fw-medium">{{ $loanApplication->submitted_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clipboard-check me-2"></i>Eligibility Snapshot</h6>
                    </div>
                    <div class="card-body">
                        @php $snap = $loanApplication->eligibility_snapshot; @endphp
                        @if($snap)
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" style="width:45%">Eligible</td><td><span class="badge bg-{{ ($snap['eligible'] ?? false) ? 'success' : 'danger' }}">{{ ($snap['eligible'] ?? false) ? 'Yes' : 'No' }}</span></td></tr>
                                <tr><td class="text-muted">Requested</td><td class="fw-medium">TSh {{ number_format($snap['requested_amount'] ?? 0, 0) }}</td></tr>
                                <tr><td class="text-muted">Approved Amount</td><td class="fw-medium">TSh {{ number_format($snap['approved_amount'] ?? 0, 0) }}</td></tr>
                                <tr><td class="text-muted">Total Savings</td><td class="fw-medium">TSh {{ number_format($snap['total_savings'] ?? 0, 0) }}</td></tr>
                                <tr><td class="text-muted">Total Shares</td><td class="fw-medium">TSh {{ number_format($snap['total_shares'] ?? 0, 0) }}</td></tr>
                                <tr><td class="text-muted">Failure Reasons</td><td class="fw-medium">{{ implode(', ', $snap['failure_reasons'] ?? []) ?: '—' }}</td></tr>
                            </table>
                        @else
                            <div class="text-muted text-center py-3">No eligibility snapshot available.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Guarantors ({{ $loanApplication->guarantors->count() }})</h6>
                        @if(in_array($loanApplication->status->value, ['draft']))
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addGuarantorModal">
                                <i class="bi bi-plus"></i>
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @forelse($loanApplication->guarantors as $g)
                            <div class="d-flex align-items-center justify-content-between mb-2 {{ !$loop->last ? 'border-bottom pb-2' : '' }}">
                                <div>
                                    <div class="fw-medium">{{ $g->guarantorMember->full_name ?? '—' }}</div>
                                    <small class="text-muted">TSh {{ number_format($g->guaranteed_amount, 0) }}</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-{{ $g->status->value === 'accepted' ? 'success' : ($g->status->value === 'rejected' ? 'danger' : 'warning') }}">{{ $g->status->label() }}</span>
                                    @if(in_array($loanApplication->status->value, ['draft']) && $g->status->value === 'pending')
                                        <form method="POST" action="{{ route('member.loans.remove-guarantor', [$loanApplication, $g]) }}" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this guarantor?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-3">
                                No guarantors added yet.
                                @if($loanApplication->loanPlan->requires_guarantor)
                                    <div class="small">This plan requires {{ $loanApplication->loanPlan->minimum_guarantors }} guarantor(s).</div>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-building me-2"></i>Collateral ({{ $loanApplication->collaterals->count() }})</h6>
                        @if(in_array($loanApplication->status->value, ['draft']))
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCollateralModal">
                                <i class="bi bi-plus"></i>
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @forelse($loanApplication->collaterals as $c)
                            <div class="d-flex align-items-center justify-content-between mb-2 {{ !$loop->last ? 'border-bottom pb-2' : '' }}">
                                <div>
                                    <div class="fw-medium">{{ $c->collateral_type->label() }}</div>
                                    <small class="text-muted">{{ Str::limit($c->description, 40) }} | TSh {{ number_format($c->estimated_value, 0) }}</small>
                                </div>
                                <span class="badge bg-{{ $c->status->value === 'verified' ? 'success' : ($c->status->value === 'rejected' ? 'danger' : 'warning') }}">{{ $c->status->label() }}</span>
                            </div>
                        @empty
                            <div class="text-muted text-center py-3">
                                No collateral added yet.
                                @if($loanApplication->loanPlan->requires_collateral)
                                    <div class="small">This plan requires collateral.</div>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Approval Progress</h6>
                    </div>
                    <div class="card-body">
                        @forelse($loanApplication->approvals as $a)
                            <div class="d-flex align-items-start mb-3 {{ !$loop->last ? 'border-bottom pb-3' : '' }}">
                                <div class="bg-{{ $a->action->value === 'approved' ? 'success' : ($a->action->value === 'rejected' ? 'danger' : 'secondary') }} rounded-circle d-flex align-items-center justify-content-center me-3" style="width:36px;height:36px;">
                                    <i class="bi bi-{{ $a->action->value === 'approved' ? 'check-lg' : ($a->action->value === 'rejected' ? 'x-lg' : 'clock') }} text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-medium">Level {{ $a->approval_level }} — {{ ucfirst($a->action->value) }}</div>
                                    <small class="text-muted">{{ $a->acted_at?->format('d M Y H:i') ?? '—' }}</small>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-3">No approval records yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('member.loans.applications') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <div>
                @if(in_array($loanApplication->status->value, ['draft']))
                    <form method="POST" action="{{ route('member.loans.submit', $loanApplication) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success me-2" onclick="return confirm('Submit this application for review?')">
                            <i class="bi bi-send me-1"></i> Submit Application
                        </button>
                    </form>
                @endif
                @if(in_array($loanApplication->status->value, ['draft', 'submitted']))
                    <form method="POST" action="{{ route('member.loans.cancel', $loanApplication) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this application?')">
                            <i class="bi bi-x-circle me-1"></i> Cancel Application
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@if(in_array($loanApplication->status->value, ['draft']))
<div class="modal fade" id="addGuarantorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('member.loans.add-guarantor', $loanApplication) }}">
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
                            @foreach(\App\Models\Member::where('organization_id', $member->organization_id)->active()->where('id', '!=', $member->id)->get() as $m)
                                <option value="{{ $m->id }}">{{ $m->full_name }} ({{ $m->member_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Guaranteed Amount (TSh) <span class="text-danger">*</span></label>
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

<div class="modal fade" id="addCollateralModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('member.loans.add-collateral', $loanApplication) }}">
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
                        <label class="form-label">Estimated Value (TSh) <span class="text-danger">*</span></label>
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
