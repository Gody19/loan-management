@extends('layouts.app')

@section('title', 'Loan Eligibility Check')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Loan Eligibility Check',
        'subtitle' => 'Verify member eligibility for loan plans',
        'breadcrumb' => [
            ['label' => 'Loans', 'url' => '#'],
            ['label' => 'Eligibility Check'],
        ],
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('loan-eligibility.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-6">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or member number..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('loan-eligibility.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Member No</th>
                        <th>Status</th>
                        <th>Organization</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $member->full_name }}</div>
                                <small class="text-muted">{{ $member->email ?? '—' }}</small>
                            </td>
                            <td><span class="badge bg-primary">{{ $member->member_number }}</span></td>
                            <td>
                                <span class="badge bg-{{ $member->membership_status === \App\Enums\MemberStatus::Active ? 'success' : 'secondary' }}">
                                    {{ $member->membership_status->label() }}
                                </span>
                            </td>
                            <td>{{ $member->organization->name ?? '—' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="openEligibilityModal({{ $member->id }}, '{{ addslashes($member->full_name) }}')"
                                    title="Check Eligibility">
                                    <i class="bi bi-shield-check me-1"></i> Check
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No active members found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $members->firstItem() ?? 0 }} to {{ $members->lastItem() ?? 0 }} of {{ $members->total() }} members
            </div>
            {{ $members->links() }}
        </div>

    </div>
</div>

{{-- Eligibility Check Modal --}}
<div class="modal fade" id="eligibilityModal" tabindex="-1" aria-labelledby="eligibilityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('loan-eligibility.check') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="eligibilityModalLabel">Check Loan Eligibility</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="member_id" id="modalMemberId">

                    <div class="mb-3">
                        <label class="form-label fw-medium">Member</label>
                        <input type="text" class="form-control" id="modalMemberName" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Loan Plan <span class="text-danger">*</span></label>
                        <select name="loan_plan_id" class="form-select" required>
                            <option value="">Select Loan Plan</option>
                            @foreach(\App\Models\LoanPlan::active()->get() as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Requested Amount (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="requested_amount" class="form-control" step="0.01" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Term (months)</label>
                        <input type="number" name="term_months" class="form-control" min="1">
                        <div class="form-text">Optional — leave blank to skip term check.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-check me-1"></i> Run Eligibility Check
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEligibilityModal(memberId, memberName) {
    document.getElementById('modalMemberId').value = memberId;
    document.getElementById('modalMemberName').value = memberName;
    new bootstrap.Modal(document.getElementById('eligibilityModal')).show();
}
</script>
@endpush
