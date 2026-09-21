@extends('layouts.member')

@section('title', 'Apply for ' . $loanPlan->name . ' - FinancePro VICOBA')
@section('page-title', 'Apply for ' . $loanPlan->name)


@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        @if($pendingApplication)
            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div>
                    You already have a <strong>{{ $pendingApplication->status->label() }}</strong> application for this plan.
                    <a href="{{ route('member.loans.application', $pendingApplication) }}" class="alert-link">View Application</a>
                </div>
            </div>
        @endif

        @if(!$eligibility->eligible)
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="bi bi-x-circle-fill me-2"></i>
                <div>
                    You are not currently eligible for this loan plan.
                    <a href="{{ route('member.loans.eligibility', $loanPlan) }}" class="alert-link">View Eligibility Details</a>
                </div>
            </div>
        @endif

        @if($activeLoanCount >= $loanPlan->maximum_active_loans)
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <div>You have reached the maximum number of active loans ({{ $loanPlan->maximum_active_loans }}) for this plan.</div>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2"></i>Loan Plan Summary</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Plan</div>
                        <div class="fw-medium">{{ $loanPlan->name }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Amount Range</div>
                        <div class="fw-medium">TSh {{ number_format($loanPlan->minimum_amount, 0) }} - {{ number_format($loanPlan->maximum_amount, 0) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Term Range</div>
                        <div class="fw-medium">{{ $loanPlan->minimum_term }} - {{ $loanPlan->maximum_term }} months</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Interest</div>
                        <div class="fw-medium">{{ $loanPlan->interest_rate }}% ({{ $loanPlan->interest_method->label() }})</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Frequency</div>
                        <div class="fw-medium">{{ $loanPlan->repayment_frequency->label() }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Processing Fee</div>
                        <div class="fw-medium">{{ $loanPlan->processing_fee }}%</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pencil-square me-2"></i>Application Details</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('member.loans.store', $loanPlan) }}" id="loanApplicationForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="requested_amount" class="form-label">Loan Amount (TSh) <span class="text-danger">*</span></label>
                            <input type="number" name="requested_amount" id="requested_amount" class="form-control @error('requested_amount') is-invalid @enderror"
                                   value="{{ old('requested_amount', $loanPlan->maximum_amount) }}"
                                   min="{{ $loanPlan->minimum_amount }}" max="{{ $loanPlan->maximum_amount }}" step="0.01" required>
                            @error('requested_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Minimum: TSh {{ number_format($loanPlan->minimum_amount, 0) }} | Maximum: TSh {{ number_format($loanPlan->maximum_amount, 0) }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="requested_term" class="form-label">Loan Term (Months) <span class="text-danger">*</span></label>
                            <input type="number" name="requested_term" id="requested_term" class="form-control @error('requested_term') is-invalid @enderror"
                                   value="{{ old('requested_term', $loanPlan->maximum_term) }}"
                                   min="{{ $loanPlan->minimum_term }}" max="{{ $loanPlan->maximum_term }}" required>
                            @error('requested_term')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Minimum: {{ $loanPlan->minimum_term }} months | Maximum: {{ $loanPlan->maximum_term }} months</div>
                        </div>
                        <div class="col-md-6">
                            <label for="loan_purpose" class="form-label">Loan Purpose <span class="text-danger">*</span></label>
                            <select name="loan_purpose" id="loan_purpose" class="form-select @error('loan_purpose') is-invalid @enderror" required>
                                <option value="">Select Purpose</option>
                                @foreach(\App\Enums\LoanPurpose::cases() as $purpose)
                                    <option value="{{ $purpose->value }}" {{ old('loan_purpose') === $purpose->value ? 'selected' : '' }}>{{ $purpose->label() }}</option>
                                @endforeach
                            </select>
                            @error('loan_purpose')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="purpose_description" class="form-label">Purpose Description</label>
                            <textarea name="purpose_description" id="purpose_description" class="form-control @error('purpose_description') is-invalid @enderror"
                                      rows="3" maxlength="1000" placeholder="Describe the specific purpose of this loan...">{{ old('purpose_description') }}</textarea>
                            @error('purpose_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @if($loanPlan->requires_guarantor)
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            This plan requires <strong>{{ $loanPlan->minimum_guarantors }} guarantor(s)</strong>.
                            You can add guarantors after submitting your application.
                        </div>
                    @endif

                    @if($loanPlan->requires_collateral)
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            This plan requires <strong>collateral</strong>.
                            You can add collateral details after submitting your application.
                        </div>
                    @endif

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('member.loans.plan', $loanPlan) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" {{ !$eligibility->eligible || $pendingApplication || $activeLoanCount >= $loanPlan->maximum_active_loans ? 'disabled' : '' }}>
                            <i class="bi bi-send me-1"></i> Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('loanApplicationForm').addEventListener('submit', function(e) {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';
});
</script>
@endpush
@endsection
