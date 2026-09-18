@extends('layouts.member')

@section('title', 'My Loans - FinancePro VICOBA')
@section('page-title', 'My Loans')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">My Loans</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="stat-label">Active Loans</div>
                        <div class="stat-value">{{ $loanDetails->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <div class="stat-label">Outstanding Balance</div>
                        <div class="stat-value">TSh {{ number_format($loanDetails->sum('outstanding_balance'), 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="stat-label">Total Paid</div>
                        <div class="stat-value">TSh {{ number_format($loanDetails->sum('amount_paid'), 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3"><i class="bi bi-check2-all"></i></div>
                    <div>
                        <div class="stat-label">Completed Loans</div>
                        <div class="stat-value">{{ $completedLoans->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($loanDetails->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>Active Loans</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan Number</th>
                        <th>Plan</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loanDetails as $loan)
                    <tr>
                        <td class="fw-medium">{{ $loan['loan_number'] }}</td>
                        <td>{{ $loan['plan_name'] }}</td>
                        <td class="text-end">TSh {{ number_format($loan['principal_amount'], 2) }}</td>
                        <td class="text-end text-success">TSh {{ number_format($loan['amount_paid'], 2) }}</td>
                        <td class="text-end fw-medium text-danger">TSh {{ number_format($loan['outstanding_balance'], 2) }}</td>
                        <td>
                            @php
                                $progress = $loan['total_installments'] > 0 ? ($loan['installments_paid'] / $loan['total_installments'] * 100) : 0;
                            @endphp
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                                </div>
                                <small class="text-muted">{{ $loan['installments_paid'] }}/{{ $loan['total_installments'] }}</small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $loan['status']->color() }}">
                                {{ $loan['status']->label() }}
                            </span>
                            @if($loan['days_past_due'] > 0)
                                <br><small class="text-danger">{{ $loan['days_past_due'] }} DPD</small>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($completedLoans->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-check2-all me-2 text-secondary"></i>Completed Loans</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan Number</th>
                        <th>Plan</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Total Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completedLoans as $loan)
                    <tr>
                        <td class="fw-medium">{{ $loan->loan_number }}</td>
                        <td>{{ $loan->loanPlan->name ?? '-' }}</td>
                        <td class="text-end">TSh {{ number_format($loan->principal_amount, 2) }}</td>
                        <td class="text-end">TSh {{ number_format($loan->total_amount, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $loan->status->color() }}">
                                {{ $loan->status->label() }}
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

@if($recentRepayments->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Repayments</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Loan Number</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentRepayments as $repayment)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($repayment['date'])->format('d M Y') }}</td>
                        <td class="fw-medium">{{ $repayment['repayment_number'] }}</td>
                        <td>{{ $repayment['loan_number'] }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($repayment['amount'], 2) }}</td>
                        <td>
                            @php
                                $statusColors = ['Posted' => 'success', 'Reversed' => 'danger'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$repayment['status']] ?? 'secondary' }}">
                                {{ $repayment['status'] }}
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

@if($loanDetails->count() === 0 && $completedLoans->count() === 0)
<div class="card vicoba-card">
    <div class="card-body text-center py-5">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-cash-coin text-muted" style="font-size: 2rem;"></i>
        </div>
        <h6 class="text-muted fw-semibold">No loans</h6>
        <p class="text-muted small mb-0">You do not have any loans yet.</p>
    </div>
</div>
@endif
@endsection
