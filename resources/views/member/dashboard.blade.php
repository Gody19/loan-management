@extends('layouts.member')

@section('title', 'My Dashboard - FinancePro VICOBA')
@section('page-title', 'My Dashboard')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item active">My Dashboard</li>
        </ol>
    </nav>
@endsection

@section('content')
{{-- Member Identity --}}
<div class="card vicoba-card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px;">
                <i class="bi bi-person text-white fs-4"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-0 fw-bold">{{ $member->full_name }}</h5>
                <div class="d-flex align-items-center gap-3 mt-1">
                    <span class="text-muted"><i class="bi bi-hash me-1"></i>{{ $member->member_number }}</span>
                    <span class="badge bg-success">{{ ucfirst($member->membership_status->value) }}</span>
                </div>
            </div>
            <div class="d-none d-md-flex gap-2">
                <a href="{{ route('member.loans') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-cash-coin me-1"></i> Apply for Loan
                </a>
                @if($loans['active_count'] > 0)
                    <a href="{{ route('member.loans.show', $loans['all_loans']->firstWhere(fn($l) => in_array($l->status->value, ['active', 'disbursed', 'pending_disbursement']))) }}" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-eye me-1"></i> View Active Loan
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- My Loans --}}
<h6 class="mb-3 fw-semibold"><i class="bi bi-cash-coin me-2"></i>My Loans</h6>

{{-- Loan Financial Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100 border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="stat-label">Active Loans</div>
                        <div class="stat-value">{{ $loans['active_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100 border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="stat-label">Outstanding Balance</div>
                        <div class="stat-value">TSh {{ number_format($loans['total_outstanding'], 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100 border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="stat-label">Total Paid</div>
                        <div class="stat-value">TSh {{ number_format($loans['total_paid'], 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100 border-start border-{{ $loans['overdue_amount'] > 0 ? 'warning' : 'info' }} border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-{{ $loans['overdue_amount'] > 0 ? 'warning' : 'info' }} bg-opacity-10 text-{{ $loans['overdue_amount'] > 0 ? 'warning' : 'info' }} me-3">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stat-label">Overdue Amount</div>
                        <div class="stat-value">TSh {{ number_format($loans['overdue_amount'], 0) }}</div>
                        @if($loans['overdue_installments'] > 0)
                            <small class="text-danger">{{ $loans['overdue_installments'] }} overdue installment(s)</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Next Payment & Application Status --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100 border-start border-dark border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-dark bg-opacity-10 text-dark me-3"><i class="bi bi-calendar-event"></i></div>
                    <div>
                        <div class="stat-label">Next Installment</div>
                        @if($loans['next_installment'])
                            <div class="stat-value">TSh {{ number_format($loans['next_installment']->total_amount, 0) }}</div>
                            <small class="text-muted">Due {{ \Carbon\Carbon::parse($loans['next_installment']->due_date)->format('d M Y') }}</small>
                        @else
                            <div class="stat-value text-muted">—</div>
                            <small class="text-muted">No pending installments</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100 border-start border-secondary border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3"><i class="bi bi-file-text"></i></div>
                    <div>
                        <div class="stat-label">Pending Applications</div>
                        <div class="stat-value">{{ $pendingApplications }}</div>
                        @if($pendingApplications > 0)
                            <small class="text-muted">Awaiting review</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <a href="{{ route('member.guarantor.requests') }}" class="text-decoration-none">
            <div class="card vicoba-card h-100 border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <div class="stat-label">Guarantor Requests</div>
                            <div class="stat-value">{{ $pendingGuarantorRequests }}</div>
                            @if($pendingGuarantorRequests > 0)
                                <small class="text-danger">Pending your response</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6">
        <a href="{{ route('member.repayment-schedule') }}" class="text-decoration-none">
            <div class="card vicoba-card h-100 border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-calendar3"></i></div>
                        <div>
                            <div class="stat-label">Repayment Schedule</div>
                            <div class="stat-value">{{ $loans['active_count'] }}</div>
                            <small class="text-muted">Active loan(s)</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- Savings & Shares Summary --}}
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100 border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-piggy-bank"></i></div>
                    <div>
                        <div class="stat-label">My Savings Accounts</div>
                        <div class="stat-value">TSh {{ number_format($savings['total_balance'], 2) }}</div>
                        <small class="text-muted">{{ $savings['accounts_count'] }} account(s)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100 border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-bar-chart"></i></div>
                    <div>
                        <div class="stat-label">My Share Accounts</div>
                        <div class="stat-value">TSh {{ number_format($shares['total_value'], 2) }}</div>
                        <small class="text-muted">{{ number_format($shares['total_shares']) }} shares</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100 border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <div class="stat-label">My Welfare Accounts</div>
                        <div class="stat-value">TSh {{ number_format($welfare['total_balance'], 2) }}</div>
                        <small class="text-muted">{{ $welfare['accounts_count'] }} account(s)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <a href="{{ route('member.loans') }}" class="card vicoba-card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-cash-coin fs-3 text-primary d-block mb-1"></i>
                <div class="fw-semibold small">Apply for Loan</div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        @if($loans['active_count'] > 0)
            <a href="{{ route('member.loans.show', $loans['all_loans']->firstWhere(fn($l) => in_array($l->status->value, ['active', 'disbursed']))) }}" class="card vicoba-card text-decoration-none h-100">
                <div class="card-body text-center">
                    <i class="bi bi-calendar3 fs-3 text-success d-block mb-1"></i>
                    <div class="fw-semibold small">View Schedule</div>
                </div>
            </a>
        @else
            <div class="card vicoba-card h-100 opacity-50">
                <div class="card-body text-center">
                    <i class="bi bi-calendar3 fs-3 text-muted d-block mb-1"></i>
                    <div class="fw-semibold small text-muted">View Schedule</div>
                </div>
            </div>
        @endif
    </div>
    <div class="col-md-3 col-6">
        <a href="{{ route('member.repayments') }}" class="card vicoba-card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-clock-history fs-3 text-warning d-block mb-1"></i>
                <div class="fw-semibold small">Payment History</div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="{{ route('member.guarantor.requests') }}" class="card vicoba-card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-people fs-3 text-info d-block mb-1"></i>
                <div class="fw-semibold small">Guarantor Requests</div>
            </div>
        </a>
    </div>
</div>

{{-- Pending Disbursement Notice --}}
@php
    $pendingDisbursementLoans = $loans['all_loans']->filter(fn ($l) => $l->status->value === 'pending_disbursement');
@endphp
@if($pendingDisbursementLoans->count() > 0)
@foreach($pendingDisbursementLoans as $pdl)
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-hourglass-split fs-4 me-3"></i>
    <div>
        <strong>Loan {{ $pdl->loan_number }} is pending disbursement.</strong><br>
        <small>Your loan application has been approved. The disbursement is being processed. Once confirmed, your repayment schedule will become active.</small>
    </div>
</div>
@endforeach
@endif

{{-- Upcoming Payments --}}
@if($loans['upcoming_payments']->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2 text-danger"></i>Upcoming Payments</h6>
        @if($loans['active_count'] > 0)
            <a href="{{ route('member.repayment-schedule') }}" class="btn btn-outline-primary btn-sm">View Full Schedule</a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Due Date</th>
                        <th>Installment</th>
                        <th class="text-end">Amount Due</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loans['upcoming_payments'] as $payment)
                        <tr class="{{ $payment->status === 'overdue' ? 'table-danger' : '' }}">
                            <td class="fw-medium">{{ \Carbon\Carbon::parse($payment->due_date)->format('d M Y') }}</td>
                            <td>Installment #{{ $payment->installment_number }}</td>
                            <td class="text-end">TSh {{ number_format($payment->total_amount, 0) }}</td>
                            <td class="text-end text-success">TSh {{ number_format($payment->amount_paid, 0) }}</td>
                            <td class="text-end text-danger fw-medium">TSh {{ number_format($payment->outstanding_amount, 0) }}</td>
                            <td>
                                @php
                                    $statusColors = ['pending' => 'secondary', 'partial' => 'warning', 'overdue' => 'danger'];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$payment->status] ?? 'secondary' }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- My Organization Info --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-building"></i></div>
                    <div>
                        <div class="stat-label">Organization</div>
                        <div class="fw-semibold">{{ $member->organization?->name ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-geo-alt"></i></div>
                    <div>
                        <div class="stat-label">Branch</div>
                        <div class="fw-semibold">{{ $member->branch?->name ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-diagram-3"></i></div>
                    <div>
                        <div class="stat-label">VICOBA Group</div>
                        <div class="fw-semibold">{{ $member->vicobaGroup?->name ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Active Loans --}}
@if($loans['all_loans']->filter(fn($l) => in_array($l->status->value, ['active', 'disbursed']))->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>My Active Loans</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan Number</th>
                        <th>Plan</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loans['all_loans']->filter(fn($l) => in_array($l->status->value, ['active', 'disbursed'])) as $loan)
                    @php
                        $totalDue = (float) $loan->total_amount;
                        $paid = (float) $loan->amount_paid;
                        $progress = $totalDue > 0 ? round(($paid / $totalDue) * 100) : 0;
                    @endphp
                    <tr>
                        <td class="fw-medium">{{ $loan->loan_number }}</td>
                        <td>{{ $loan->loanPlan->name ?? '-' }}</td>
                        <td class="text-end">TSh {{ number_format($loan->principal_amount, 0) }}</td>
                        <td class="text-end text-success">TSh {{ number_format($loan->amount_paid, 0) }}</td>
                        <td class="text-end fw-medium text-danger">TSh {{ number_format($loan->outstanding_balance, 0) }}</td>
                        <td style="min-width: 120px">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                            </div>
                            <small class="text-muted">{{ $progress }}%</small>
                        </td>
                        <td>
                            <span class="badge bg-{{ $loan->status->value === 'active' ? 'success' : 'info' }}">
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

{{-- Recent Activity --}}
@if(count($recentTransactions) > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTransactions as $tx)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tx->date)->format('d M Y') }}</td>
                        <td class="fw-medium">{{ $tx->reference }}</td>
                        <td>
                            @php
                                $catColors = ['savings' => 'success', 'shares' => 'warning', 'welfare' => 'info', 'loan_repayment' => 'primary'];
                                $catLabels = ['savings' => 'Savings', 'shares' => 'Shares', 'welfare' => 'Welfare', 'loan_repayment' => 'Loan Repayment'];
                            @endphp
                            <span class="badge bg-{{ $catColors[$tx->category] ?? 'secondary' }}">
                                {{ $catLabels[$tx->category] ?? $tx->category }}
                            </span>
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $tx->type)) }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($tx->amount, 2) }}</td>
                        <td>
                            @php
                                $statusColors = ['completed' => 'success', 'confirmed' => 'success', 'pending' => 'warning', 'reversed' => 'danger', 'posted' => 'success'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$tx->status] ?? 'secondary' }}">
                                {{ ucfirst($tx->status) }}
                            </span>
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
