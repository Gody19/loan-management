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
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="stat-label">My Savings</div>
                        <div class="stat-value">TSh {{ number_format($savings['total_balance'], 0) }}</div>
                        <small class="text-muted">{{ $savings['accounts_count'] }} account(s)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="stat-label">My Shares</div>
                        <div class="stat-value">{{ number_format($shares['total_shares'], 0) }}</div>
                        <small class="text-muted">Value: TSh {{ number_format($shares['total_value'], 0) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-heart"></i></div>
                    <div>
                        <div class="stat-label">My Welfare</div>
                        <div class="stat-value">TSh {{ number_format($welfare['total_balance'], 0) }}</div>
                        <small class="text-muted">{{ $welfare['accounts_count'] }} account(s)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="stat-label">My Loans</div>
                        <div class="stat-value">{{ $loans['active_count'] }}</div>
                        <small class="text-muted">Outstanding: TSh {{ number_format($loans['total_outstanding'], 0) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Next Payment --}}
@if($loans['next_installment'])
<div class="card vicoba-card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-calendar-event"></i></div>
            <div class="flex-grow-1">
                <div class="fw-semibold">Next Payment Due</div>
                <div class="text-muted small">
                    TSh {{ number_format($loans['next_installment']->total_amount, 0) }} due on
                    {{ \Carbon\Carbon::parse($loans['next_installment']->due_date)->format('d M Y') }}
                </div>
            </div>
            <div class="text-end">
                <small class="text-muted">Installment #{{ $loans['next_installment']->installment_number }}</small>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Savings Accounts --}}
@if($savings['accounts']->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2 text-success"></i>My Savings Accounts</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account Number</th>
                        <th>Plan</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($savings['accounts'] as $account)
                    <tr>
                        <td class="fw-medium">{{ $account->account_number }}</td>
                        <td>{{ $account->product->name ?? '-' }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($account->current_balance, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $account->status->value === 'active' ? 'success' : 'secondary' }}">
                                {{ $account->status->label() }}
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

{{-- Share Accounts --}}
@if($shares['accounts']->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2 text-warning"></i>My Share Accounts</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account Number</th>
                        <th>Plan</th>
                        <th class="text-end">Shares</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shares['accounts'] as $account)
                    <tr>
                        <td class="fw-medium">{{ $account->account_number }}</td>
                        <td>{{ $account->product->name ?? '-' }}</td>
                        <td class="text-end">{{ number_format($account->total_shares, 0) }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($account->total_value, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $account->status->value === 'active' ? 'success' : 'secondary' }}">
                                {{ $account->status->label() }}
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

{{-- Welfare Accounts --}}
@if($welfare['accounts']->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-heart me-2 text-info"></i>My Welfare Accounts</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account Number</th>
                        <th>Fund</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($welfare['accounts'] as $account)
                    <tr>
                        <td class="fw-medium">{{ $account->account_number }}</td>
                        <td>{{ $account->fund->name ?? '-' }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($account->current_balance, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $account->status->value === 'active' ? 'success' : 'secondary' }}">
                                {{ $account->status->label() }}
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

{{-- Active Loans --}}
@if($loans['all_loans']->filter(fn($l) => in_array($l->status->value, ['active', 'disbursed']))->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>My Active Loans</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan Number</th>
                        <th>Plan</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loans['all_loans']->filter(fn($l) => in_array($l->status->value, ['active', 'disbursed'])) as $loan)
                    <tr>
                        <td class="fw-medium">{{ $loan->loan_number }}</td>
                        <td>{{ $loan->loanPlan->name ?? '-' }}</td>
                        <td class="text-end">TSh {{ number_format($loan->principal_amount, 0) }}</td>
                        <td class="text-end fw-medium text-danger">TSh {{ number_format($loan->outstanding_balance, 0) }}</td>
                        <td>
                            <span class="badge bg-{{ $loan->status->value === 'active' ? 'success' : 'info' }}">
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

{{-- Recent Transactions --}}
@if(count($recentTransactions) > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
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
                                $statusColors = ['completed' => 'success', 'confirmed' => 'success', 'pending' => 'warning', 'reversed' => 'danger'];
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
