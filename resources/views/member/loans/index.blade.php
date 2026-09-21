@extends('layouts.member')

@section('title', 'My Loans - FinancePro VICOBA')
@section('page-title', 'My Loans')


@section('content')
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="stat-label">Active Loans</div>
                        <div class="stat-value">{{ $activeLoans->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="stat-label">Pending Applications</div>
                        <div class="stat-value">{{ $applications->whereIn('status.value', ['draft', 'submitted', 'under_review'])->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-file-text"></i></div>
                    <div>
                        <div class="stat-label">Total Applications</div>
                        <div class="stat-value">{{ $applications->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-book me-2"></i>Available Loan Plans</h6>
    </div>
    <div class="card-body">
        @if($plans->isEmpty())
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No loan plans available for your organization.
            </div>
        @else
            <div class="row g-3">
                @foreach($plans as $plan)
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border">
                            <div class="card-body">
                                <h6 class="card-title fw-bold">{{ $plan->name }}</h6>
                                <div class="mb-2"><span class="badge bg-primary">{{ $plan->loan_purpose->label() }}</span></div>
                                <div class="small text-muted mb-2">{{ Str::limit($plan->description, 80) }}</div>
                                <table class="table table-borderless table-sm small mb-2">
                                    <tr><td class="text-muted">Amount</td><td class="fw-medium">TSh {{ number_format($plan->minimum_amount, 0) }} - {{ number_format($plan->maximum_amount, 0) }}</td></tr>
                                    <tr><td class="text-muted">Interest</td><td class="fw-medium">{{ $plan->interest_rate }}% ({{ $plan->interest_method->label() }})</td></tr>
                                    <tr><td class="text-muted">Term</td><td class="fw-medium">{{ $plan->minimum_term }} - {{ $plan->maximum_term }} months</td></tr>
                                    <tr><td class="text-muted">Frequency</td><td class="fw-medium">{{ $plan->repayment_frequency->label() }}</td></tr>
                                </table>
                                @if($plan->requires_guarantor)
                                    <div class="small mb-1"><i class="bi bi-people text-warning me-1"></i> Requires {{ $plan->minimum_guarantors }} guarantor(s)</div>
                                @endif
                                @if($plan->requires_collateral)
                                    <div class="small mb-1"><i class="bi bi-building text-info me-1"></i> Requires collateral</div>
                                @endif
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('member.loans.plan', $plan) }}" class="btn btn-outline-primary btn-sm flex-grow-1">View Details</a>
                                    <a href="{{ route('member.loans.apply', $plan) }}" class="btn btn-primary btn-sm flex-grow-1">Apply Now</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@if($activeLoans->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2"></i>Active Loans</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan #</th>
                        <th>Plan</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activeLoans as $loan)
                        <tr>
                            <td><span class="badge bg-primary">{{ $loan->loan_number }}</span></td>
                            <td>{{ $loan->loanPlan->name ?? '—' }}</td>
                            <td class="text-end">TSh {{ number_format($loan->principal_amount, 0) }}</td>
                            <td class="text-end">TSh {{ number_format($loan->amount_paid, 0) }}</td>
                            <td class="text-end">TSh {{ number_format($loan->outstanding_balance, 0) }}</td>
                            <td><span class="badge bg-{{ $loan->status->color() }}">{{ $loan->status->label() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-file-text me-2"></i>My Applications</h6>
        @if($applications->isNotEmpty())
            <a href="{{ route('member.loans.applications') }}" class="btn btn-outline-primary btn-sm">View All</a>
        @endif
    </div>
    <div class="card-body">
        @if($applications->isEmpty())
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No loan applications yet.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Application #</th>
                            <th>Plan</th>
                            <th class="text-end">Amount</th>
                            <th>Term</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applications->take(5) as $app)
                            <tr>
                                <td><span class="badge bg-primary">{{ $app->application_number }}</span></td>
                                <td>{{ $app->loanPlan->name ?? '—' }}</td>
                                <td class="text-end">TSh {{ number_format($app->requested_amount, 0) }}</td>
                                <td>{{ $app->requested_term }} mo</td>
                                <td>{{ $app->application_date?->format('d M Y') ?? '—' }}</td>
                                <td><span class="badge bg-{{ $app->status->color() }}">{{ $app->status->label() }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('member.loans.application', $app) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Completed Loans --}}
@if(isset($completedLoans) && $completedLoans->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2 text-success"></i>Completed Loans ({{ $completedLoans->count() }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan #</th>
                        <th>Plan</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Total Paid</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completedLoans as $loan)
                        <tr>
                            <td><span class="badge bg-primary">{{ $loan->loan_number }}</span></td>
                            <td>{{ $loan->loanPlan->name ?? '—' }}</td>
                            <td class="text-end">TSh {{ number_format($loan->principal_amount, 0) }}</td>
                            <td class="text-end text-success">TSh {{ number_format($loan->amount_paid, 0) }}</td>
                            <td>
                                <span class="badge bg-{{ $loan->status->value === 'completed' ? 'success' : 'secondary' }}">
                                    {{ $loan->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('member.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
