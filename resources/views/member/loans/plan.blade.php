@extends('layouts.member')

@section('title', $loanPlan->name . ' - FinancePro VICOBA')
@section('page-title', $loanPlan->name)


@section('content')
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-cash-coin text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $loanPlan->name }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-primary">{{ $loanPlan->loan_purpose->label() }}</span>
                            <span class="badge bg-{{ $loanPlan->status->color() }}">{{ $loanPlan->status->label() }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        @if($eligibility->eligible)
                            <span class="badge bg-success fs-6 mb-1">Eligible</span>
                        @else
                            <span class="badge bg-danger fs-6 mb-1">Not Eligible</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Loan Terms</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:50%">Loan Amount</td><td class="fw-medium">TSh {{ number_format($loanPlan->minimum_amount, 0) }} - {{ number_format($loanPlan->maximum_amount, 0) }}</td></tr>
                            <tr><td class="text-muted">Interest Rate</td><td class="fw-medium">{{ $loanPlan->interest_rate }}%</td></tr>
                            <tr><td class="text-muted">Interest Method</td><td class="fw-medium">{{ $loanPlan->interest_method->label() }}</td></tr>
                            <tr><td class="text-muted">Repayment Frequency</td><td class="fw-medium">{{ $loanPlan->repayment_frequency->label() }}</td></tr>
                            <tr><td class="text-muted">Minimum Term</td><td class="fw-medium">{{ $loanPlan->minimum_term }} months</td></tr>
                            <tr><td class="text-muted">Maximum Term</td><td class="fw-medium">{{ $loanPlan->maximum_term }} months</td></tr>
                            <tr><td class="text-muted">Grace Period</td><td class="fw-medium">{{ $loanPlan->grace_period }} days</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clipboard-check me-2"></i>Requirements</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:50%">Max Active Loans</td><td class="fw-medium">{{ $loanPlan->maximum_active_loans }}</td></tr>
                            <tr><td class="text-muted">Guarantor Required</td><td class="fw-medium">{{ $loanPlan->requires_guarantor ? $loanPlan->minimum_guarantors . ' guarantor(s)' : 'No' }}</td></tr>
                            <tr><td class="text-muted">Collateral Required</td><td class="fw-medium">{{ $loanPlan->requires_collateral ? 'Yes' : 'No' }}</td></tr>
                            <tr><td class="text-muted">Processing Fee</td><td class="fw-medium">{{ $loanPlan->processing_fee }}%</td></tr>
                            <tr><td class="text-muted">Insurance Fee</td><td class="fw-medium">{{ $loanPlan->insurance_fee }}%</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2"></i>Eligibility Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($eligibility->checks as $check => $result)
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        @if($result === 'pass')
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        @else
                                            <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                        @endif
                                        <span class="text-capitalize">{{ str_replace('_', ' ', $check) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if(!empty($eligibility->failureReasons))
                            <hr>
                            <h6 class="text-danger fw-semibold mb-2">Failure Reasons</h6>
                            <ul class="mb-0">
                                @foreach($eligibility->failureReasons as $reason)
                                    <li class="text-danger small">{{ $reason }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('member.loans') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            @if($eligibility->eligible)
                <a href="{{ route('member.loans.apply', $loanPlan) }}" class="btn btn-primary">
                    <i class="bi bi-pencil-square me-1"></i> Apply Now
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
