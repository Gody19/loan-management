@extends('layouts.member')

@section('title', 'My Guarantees')
@section('page-title', 'My Guarantees')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-{{ $eligibility['eligible'] ? 'success' : 'warning' }} rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-shield-{{ $eligibility['eligible'] ? 'check' : 'exclamation' }} text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-0 fw-bold">Guarantor Status</h5>
                        @if($eligibility['eligible'])
                            <span class="text-success fw-medium">You are eligible to guarantee another member.</span>
                        @else
                            <span class="text-warning fw-medium">{{ $eligibility['reason'] }}</span>
                        @endif
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Active Guarantees</div>
                        <div class="fw-bold fs-3 text-{{ $eligibility['active_count'] > 0 ? 'warning' : 'success' }}">{{ $eligibility['active_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- My Loan Applications — Add Guarantors --}}
        @if($myApplications->count() > 0)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person-plus me-2"></i>My Loan Applications — Guarantor Details</h6>
            </div>
            <div class="card-body">
                @foreach($myApplications as $app)
                <div class="border rounded p-4 mb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="fw-bold">{{ $app->loanPlan->name ?? '—' }}</div>
                            <small class="text-muted">
                                Applied: TSh {{ number_format($app->requested_amount, 0) }} |
                                Status: <span class="badge bg-{{ match($app->status->value) { 'draft' => 'secondary', 'submitted' => 'info', 'under_review' => 'warning', default => 'secondary' } }}">{{ $app->status->label() }}</span>
                            </small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Guarantors: {{ $app->guarantors->count() }} / {{ $app->loanPlan->minimum_guarantors ?? 0 }}</small>
                        </div>
                    </div>

                    {{-- Existing Guarantors --}}
                    @if($app->guarantors->count() > 0)
                    <div class="mb-3">
                        <h6 class="fw-semibold small text-muted mb-2">Current Guarantors</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Member #</th>
                                        <th>NIDA</th>
                                        <th>Phone</th>
                                        <th>Group</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($app->guarantors as $g)
                                    <tr>
                                        <td>{{ $g->guarantorMember->full_name ?? '—' }}</td>
                                        <td><small>{{ $g->guarantorMember->member_number ?? '—' }}</small></td>
                                        <td><small>{{ $g->guarantorMember->national_id ?? '—' }}</small></td>
                                        <td><small>{{ $g->guarantorMember->phone ?? '—' }}</small></td>
                                        <td><small>{{ $g->guarantorMember->vicobaGroup->name ?? '—' }}</small></td>
                                        <td class="fw-medium">TSh {{ number_format($g->guaranteed_amount, 0) }}</td>
                                        <td>
                                            <span class="badge bg-{{ match($g->status->value) { 'accepted' => 'success', 'rejected' => 'danger', default => 'warning' } }}">{{ $g->status->label() }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if(in_array($app->status->value, ['draft', 'submitted']) && in_array($g->status->value, ['pending', 'rejected']))
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" title="Edit"
                                                    data-bs-toggle="modal" data-bs-target="#editGuarantorModal"
                                                    data-id="{{ $g->id }}"
                                                    data-app-id="{{ $app->id }}"
                                                    data-amount="{{ $g->guaranteed_amount }}"
                                                    data-notes="{{ $g->notes ?? '' }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" action="{{ route('member.loans.remove-guarantor', [$app, $g]) }}" class="d-inline" data-confirm="Remove this guarantor?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger" title="Remove">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            @else
                                            <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- Add Guarantor Form --}}
                    @if(in_array($app->status->value, ['draft', 'submitted']) && $app->guarantors->count() < 2)
                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <h6 class="fw-semibold small mb-3"><i class="bi bi-person-plus me-1"></i>Fill Guarantor Details</h6>
                            @if($errors->any())
                                <div class="alert alert-danger small mb-3">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    @foreach($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif
                            <form method="POST" action="{{ route('member.loans.add-guarantor', $app) }}">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small">Select Member <span class="text-danger">*</span></label>
                                        <select name="guarantor_member_id" class="form-select form-select-sm" required>
                                            <option value="">-- Select a member --</option>
                                            @foreach($searchableMembers as $m)
                                                <option value="{{ $m['id'] }}">{{ $m['full_name'] }} ({{ $m['member_number'] }}) — {{ $m['group'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('guarantor_member_id') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Guaranteed Amount (TSh) <span class="text-danger">*</span></label>
                                        <input type="number" name="guaranteed_amount" class="form-control form-control-sm" step="1" min="1" required max="{{ $app->requested_amount }}">
                                        <div class="form-text">Max: TSh {{ number_format($app->requested_amount, 0) }}</div>
                                        @error('guaranteed_amount') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Notes</label>
                                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle me-1"></i> Add Guarantor
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    @elseif(in_array($app->status->value, ['draft', 'submitted']) && $app->guarantors->count() >= 2)
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Maximum 2 guarantors reached for this application.
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Active Guarantees (I guarantee others) --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Active Guarantees — I Guarantee Others ({{ $activeGuarantees->count() }})</h6>
            </div>
            <div class="card-body p-0">
                @forelse($activeGuarantees as $g)
                <div class="border-bottom p-4 {{ $loop->last ? 'border-bottom-0' : '' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fw-medium">{{ $g->application->member->full_name ?? '—' }}</div>
                            <small class="text-muted">Applicant: {{ $g->application->member->member_number ?? '—' }}</small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Loan</small>
                            <span class="fw-medium">{{ $g->application->loan?->loan_number ?? 'Pending' }}</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <small class="text-muted d-block">Your Guarantee</small>
                            <span class="fw-bold text-primary">TSh {{ number_format($g->guaranteed_amount, 0) }}</span>
                        </div>
                    </div>
                    @if($g->application->loan)
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <small class="text-muted">Outstanding: </small>
                            <span class="fw-medium text-danger">TSh {{ number_format($g->application->loan->outstanding_balance, 0) }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Status: </small>
                            <span class="badge bg-{{ $g->application->loan->status->color() }}">{{ $g->application->loan->status->label() }}</span>
                        </div>
                    </div>
                    @endif
                </div>
                @empty
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
                    No active guarantees.
                </div>
                @endforelse
            </div>
        </div>

        @if($completedGuarantees->count() > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2"></i>Completed Guarantees ({{ $completedGuarantees->count() }})</h6>
            </div>
            <div class="card-body p-0">
                @foreach($completedGuarantees as $g)
                <div class="border-bottom p-4 {{ $loop->last ? 'border-bottom-0' : '' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fw-medium">{{ $g->application->member->full_name ?? '—' }}</div>
                            <small class="text-muted">Applicant: {{ $g->application->member->member_number ?? '—' }}</small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Loan</small>
                            <span class="fw-medium">{{ $g->application->loan?->loan_number ?? '—' }}</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="badge bg-secondary">Released</span>
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

{{-- Edit Guarantor Modal --}}
<div class="modal fade" id="editGuarantorModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editGuarantorForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Guarantor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Guaranteed Amount (TSh) <span class="text-danger">*</span></label>
                        <input type="number" name="guaranteed_amount" id="editGuarantorAmount" class="form-control" step="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" id="editGuarantorNotes" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var editModal = document.getElementById('editGuarantorModal');
    editModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var appId = button.getAttribute('data-app-id');
        var amount = button.getAttribute('data-amount');
        var notes = button.getAttribute('data-notes');

        document.getElementById('editGuarantorAmount').value = amount;
        document.getElementById('editGuarantorNotes').value = notes;
        document.getElementById('editGuarantorForm').action = '/member/loans/applications/' + appId + '/guarantors/' + id;
    });
});
</script>
@endpush
