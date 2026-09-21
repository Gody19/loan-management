@extends('layouts.member')

@section('title', 'Offer as Guarantor')
@section('page-title', 'Offer as Guarantor')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        @if($hasActiveGuarantee)
        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div>
                <strong>Blocked:</strong> You currently have an active guaranteed loan that has not been fully repaid. You cannot guarantee another loan until it is completed.
            </div>
        </div>
        @endif

        @if($applications->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-shield-check text-muted" style="font-size: 2rem;"></i>
                </div>
                <h5 class="text-muted">No Open Applications</h5>
                <p class="text-muted mb-0">There are currently no loan applications in your organization that need guarantors.</p>
            </div>
        </div>
        @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2"></i>Available Applications ({{ $applications->count() }})</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Application #</th>
                                <th>Applicant</th>
                                <th>Loan Plan</th>
                                <th>Amount</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($applications as $app)
                            <tr>
                                <td><span class="fw-medium">{{ $app->application_number }}</span></td>
                                <td>{{ $app->member->full_name ?? '—' }}</td>
                                <td>{{ $app->loanPlan->name ?? '—' }}</td>
                                <td class="fw-medium">TSh {{ number_format($app->requested_amount, 0) }}</td>
                                <td>{{ $app->requested_term }} months</td>
                                <td>
                                    @php
                                        $appStatusColors = ['submitted' => 'info', 'under_review' => 'primary'];
                                    @endphp
                                    <span class="badge bg-{{ $appStatusColors[$app->status->value] ?? 'secondary' }}">{{ $app->status->label() }}</span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#offerModal-{{ $app->id }}" {{ $hasActiveGuarantee ? 'disabled' : '' }}>
                                        <i class="bi bi-shield-plus me-1"></i>Offer
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @foreach($applications as $app)
        <div class="modal fade" id="offerModal-{{ $app->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('member.guarantor.store-offer') }}">
                    @csrf
                    <input type="hidden" name="loan_application_id" value="{{ $app->id }}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Offer as Guarantor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border mb-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Application</small>
                                        <strong>{{ $app->application_number }}</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Applicant</small>
                                        <strong>{{ $app->member->full_name ?? '—' }}</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Loan Plan</small>
                                        <strong>{{ $app->loanPlan->name ?? '—' }}</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Amount</small>
                                        <strong>TSh {{ number_format($app->requested_amount, 0) }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Your NIDA Number <span class="text-danger">*</span></label>
                                <input type="text" name="nida_number" class="form-control" required minlength="6" maxlength="50"
                                       placeholder="Enter your National ID (NIDA) number"
                                       value="{{ old('nida_number', $member->national_id ?? '') }}">
                                <div class="form-text">Must be unique. Will be validated against existing records.</div>
                                @error('nida_number')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Guaranteed Amount (TSh) <span class="text-danger">*</span></label>
                                <input type="number" name="guaranteed_amount" class="form-control" required min="1"
                                       max="{{ $app->requested_amount }}" step="1"
                                       value="{{ old('guaranteed_amount', $app->requested_amount) }}"
                                       placeholder="Amount you are willing to guarantee">
                                <div class="form-text">Maximum: TSh {{ number_format($app->requested_amount, 0) }}</div>
                                @error('guaranteed_amount')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="2" maxlength="500" placeholder="Optional message to the applicant...">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to offer as a guarantor for this application?')">
                                <i class="bi bi-shield-check me-1"></i> Submit Offer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
        @endif

    </div>
</div>
@endsection
