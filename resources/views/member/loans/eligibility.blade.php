@extends('layouts.member')

@section('title', 'Eligibility Check - ' . $loanPlan->name)
@section('page-title', 'Eligibility Check')


@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-4">
                @if($result->eligible)
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-check-lg text-success fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-success">You Are Eligible!</h4>
                    <p class="text-muted mb-0">You meet all requirements for <strong>{{ $loanPlan->name }}</strong></p>
                @else
                    <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-x-lg text-danger fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-danger">Not Eligible</h4>
                    <p class="text-muted mb-0">You do not meet the requirements for <strong>{{ $loanPlan->name }}</strong></p>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clipboard-data me-2"></i>Eligibility Details</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-3">
                    <tr><td class="text-muted" style="width:40%">Member</td><td class="fw-medium">{{ $result->memberName }}</td></tr>
                    <tr><td class="text-muted">Loan Plan</td><td class="fw-medium">{{ $result->planName }}</td></tr>
                    <tr><td class="text-muted">Requested Amount</td><td class="fw-medium">TSh {{ number_format($result->requestedAmount, 0) }}</td></tr>
                    <tr><td class="text-muted">Approved Amount</td><td class="fw-medium">TSh {{ number_format($result->approvedAmount, 0) }}</td></tr>
                    <tr><td class="text-muted">Total Savings</td><td class="fw-medium">TSh {{ number_format($result->totalSavings, 0) }}</td></tr>
                    <tr><td class="text-muted">Total Shares</td><td class="fw-medium">TSh {{ number_format($result->totalShares, 0) }}</td></tr>
                    <tr><td class="text-muted">Active Loans</td><td class="fw-medium">{{ $result->activeLoanCount }}</td></tr>
                </table>

                <h6 class="fw-semibold mb-3">Checks</h6>
                <div class="row g-2">
                    @foreach($result->checks as $check => $status)
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-2 rounded {{ $status === 'pass' ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10' }}">
                                @if($status === 'pass')
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                @else
                                    <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                @endif
                                <span class="text-capitalize small">{{ str_replace('_', ' ', $check) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(!empty($result->failureReasons))
                    <hr>
                    <h6 class="text-danger fw-semibold mb-2">Failure Reasons</h6>
                    <ul class="mb-0">
                        @foreach($result->failureReasons as $reason)
                            <li class="text-danger small">{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('member.loans.plan', $loanPlan) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Plan
            </a>
            @if($result->eligible)
                <a href="{{ route('member.loans.apply', $loanPlan) }}" class="btn btn-primary">
                    <i class="bi bi-pencil-square me-1"></i> Apply Now
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
