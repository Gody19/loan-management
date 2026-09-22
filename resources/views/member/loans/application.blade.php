@extends('layouts.member')

@section('title', 'Application ' . $loanApplication->application_number . ' - FinancePro VICOBA')
@section('page-title', 'Application ' . $loanApplication->application_number)


@section('content')
<div class="row">
    <div class="col-12">
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
                        @if(in_array($loanApplication->status->value, ['draft', 'submitted']))
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addGuarantorModal">
                                <i class="bi bi-plus"></i>
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @forelse($loanApplication->guarantors as $g)
                            <div class="d-flex align-items-start justify-content-between mb-2 {{ !$loop->last ? 'border-bottom pb-2' : '' }}">
                                <div>
                                    <div class="fw-medium">{{ $g->guarantorMember->full_name ?? '—' }}</div>
                                    <small class="text-muted">TSh {{ number_format($g->guaranteed_amount, 0) }}</small>
                                    @if($g->guarantorMember?->national_id)
                                        <br><small class="text-muted font-monospace">NIDA: {{ $g->guarantorMember->national_id }}</small>
                                    @endif
                                    @if($g->guarantorMember?->phone)
                                        <br><small class="text-muted"><i class="bi bi-telephone me-1"></i>{{ $g->guarantorMember->phone }}</small>
                                    @endif
                                    @if($g->guarantorMember?->vicobaGroup)
                                        <br><small class="text-muted">Group: {{ $g->guarantorMember->vicobaGroup->name }}</small>
                                    @endif
                                    @if($g->rejection_reason)
                                        <br><small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ $g->rejection_reason }}</small>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-{{ $g->status->value === 'accepted' ? 'success' : ($g->status->value === 'rejected' ? 'danger' : 'warning') }}">{{ $g->status->label() }}</span>
                                    @if(in_array($loanApplication->status->value, ['draft', 'submitted']))
                                        @if($g->status->value === 'pending')
                                            <form method="POST" action="{{ route('member.loans.remove-guarantor', [$loanApplication, $g]) }}" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this guarantor?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @elseif($g->status->value === 'rejected')
                                            <form method="POST" action="{{ route('member.loans.remove-guarantor', [$loanApplication, $g]) }}" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Replace this rejected guarantor? The old record will be kept for history.')">
                                                    <i class="bi bi-arrow-repeat"></i> Replace
                                                </button>
                                            </form>
                                        @endif
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
                        @if(in_array($loanApplication->status->value, ['draft', 'submitted']))
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCollateralModal">
                                <i class="bi bi-plus"></i>
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @php $snapshot = $loanApplication->collateralSnapshot; @endphp
                        @if($snapshot && $snapshot->collateral_required)
                            <div class="alert alert-warning small mb-3">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Collateral Required</strong> — Min value: TSh {{ number_format($snapshot->minimum_collateral_value, 0) }}
                                | Coverage: {{ $snapshot->coverage_percentage }}%
                                | Assets: {{ $snapshot->minimum_assets }}–{{ $snapshot->maximum_assets }}
                                @if($snapshot->allowed_collateral_types)
                                    <br>Allowed types: {{ implode(', ', array_map(fn($t) => ucfirst($t), $snapshot->allowed_collateral_types)) }}
                                @endif
                                @if($snapshot->required_document_types)
                                    <br>Required docs: {{ implode(', ', array_map(fn($t) => \App\Enums\CollateralDocumentType::tryFrom($t)?->label() ?? $t, $snapshot->required_document_types)) }}
                                @endif
                            </div>
                        @endif

                        @forelse($loanApplication->collaterals as $c)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <div class="fw-medium">{{ $c->collateral_type->label() }}</div>
                                        <small class="text-muted">{{ Str::limit($c->description, 50) }} | TSh {{ number_format($c->estimated_value, 0) }}</small>
                                        @if($c->reviewed_value)
                                            <br><small class="text-primary">Reviewed value: TSh {{ number_format($c->reviewed_value, 0) }}</small>
                                        @endif
                                        @if($c->review_notes)
                                            <br><small class="text-muted">Notes: {{ $c->review_notes }}</small>
                                        @endif
                                    </div>
                                    <span class="badge bg-{{ $c->status->color() }}">{{ $c->status->label() }}</span>
                                </div>

                                {{-- Documents --}}
                                @if($c->documents->count() > 0)
                                    <div class="small mb-2">
                                        <strong>Documents:</strong>
                                        @foreach($c->documents as $doc)
                                            <span class="badge bg-light text-dark border me-1 mb-1">
                                                {{ $doc->document_type->label() }}
                                                <a href="{{ route('member.loans.collateral-document.download', $doc) }}" class="ms-1"><i class="bi bi-download"></i></a>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Upload documents for own collaterals --}}
                                @if(in_array($loanApplication->status->value, ['draft', 'submitted']))
                                    <form method="POST" action="{{ route('member.loans.collateral-document.upload', [$loanApplication, $c]) }}" enctype="multipart/form-data" class="mt-2">
                                        @csrf
                                        <div class="input-group input-group-sm">
                                            <select name="document_type" class="form-select" style="max-width:180px" required>
                                                <option value="">Doc type...</option>
                                                @foreach(\App\Enums\CollateralDocumentType::values() as $dt)
                                                    <option value="{{ $dt }}">{{ \App\Enums\CollateralDocumentType::from($dt)->label() }}</option>
                                                @endforeach
                                            </select>
                                            <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i></button>
                                        </div>
                                    </form>
                                @endif
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
                @php
                    $relatedLoan = \App\Models\Loan::where('loan_application_id', $loanApplication->id)->where('deleted_at', null)->first();
                @endphp
                @if($relatedLoan)
                    <a href="{{ route('member.loans.show', $relatedLoan) }}" class="btn btn-primary me-2">
                        <i class="bi bi-eye me-1"></i> View Loan Details
                    </a>
                    @if(in_array($relatedLoan->status->value, ['active', 'disbursed', 'pending_disbursement']))
                        <a href="{{ route('member.loans.repay', $relatedLoan) }}" class="btn btn-success me-2">
                            <i class="bi bi-cash me-1"></i> Make Payment
                        </a>
                    @endif
                @endif
@if(in_array($loanApplication->status->value, ['draft', 'submitted']))
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

@if(in_array($loanApplication->status->value, ['draft', 'submitted']))
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
                        <label class="form-label fw-semibold">Select Member <span class="text-danger">*</span></label>
                        <select name="guarantor_member_id" class="form-select" required>
                            <option value="">-- Select a member --</option>
                            @foreach(\App\Models\Member::where('organization_id', $member->organization_id)->active()->where('id', '!=', $member->id)->get() as $m)
                                <option value="{{ $m->id }}">{{ $m->full_name }} ({{ $m->member_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Guaranteed Amount (TSh) <span class="text-danger">*</span></label>
                        <input type="number" name="guaranteed_amount" class="form-control" step="1" min="1" required
                               max="{{ $loanApplication->requested_amount }}">
                        <div class="form-text">Maximum: TSh {{ number_format($loanApplication->requested_amount, 0) }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional message to the guarantor..."></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i> The selected member will be notified and must fill in their details (NIDA, contact info) and confirm/accept before the application can proceed.
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
