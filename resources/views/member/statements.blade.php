@extends('layouts.member')

@section('title', 'Financial Statement - FinancePro VICOBA')
@section('page-title', 'Financial Statement')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Financial Statement</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11 col-xl-10">

        {{-- Statement Header --}}
        <div class="card vicoba-card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-1">Member Financial Statement</h5>
                        <div class="text-muted small">
                            {{ $member->full_name }} &middot; {{ $member->member_number }}<br>
                            {{ $member->organization->name ?? '' }} &middot; {{ $member->branch->name ?? '' }}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Generated</div>
                        <div class="fw-semibold">{{ now()->format('d M Y') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- Savings Summary --}}
            <div class="col-md-6">
                <div class="card vicoba-card h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2 text-success"></i>Savings</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Total Balance</span>
                            <span class="fw-bold fs-5 text-success">TSh {{ number_format($savingsSummary['total_balance'], 2) }}</span>
                        </div>
                        @if($savingsSummary['accounts']->count() > 0)
                            @foreach($savingsSummary['accounts'] as $account)
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">{{ $account->account_number }} ({{ $account->product->name ?? '—' }})</span>
                                    <span class="fw-medium">TSh {{ number_format($account->current_balance, 2) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted small text-center py-2">No savings accounts</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Shares Summary --}}
            <div class="col-md-6">
                <div class="card vicoba-card h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2 text-warning"></i>Shares</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Shares</span>
                            <span class="fw-bold">{{ number_format($sharesSummary['total_shares']) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Total Value</span>
                            <span class="fw-bold fs-5 text-warning">TSh {{ number_format($sharesSummary['total_value'], 2) }}</span>
                        </div>
                        @if($sharesSummary['accounts']->count() > 0)
                            @foreach($sharesSummary['accounts'] as $account)
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">{{ $account->account_number }} ({{ $account->product->name ?? '—' }})</span>
                                    <span class="fw-medium">{{ number_format($account->total_shares) }} shares</span>
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted small text-center py-2">No share accounts</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Welfare Summary --}}
            <div class="col-md-6">
                <div class="card vicoba-card h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-heart me-2 text-info"></i>Welfare</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Total Balance</span>
                            <span class="fw-bold fs-5 text-info">TSh {{ number_format($welfareSummary['total_balance'], 2) }}</span>
                        </div>
                        @if($welfareSummary['accounts']->count() > 0)
                            @foreach($welfareSummary['accounts'] as $account)
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">{{ $account->account_number }} ({{ $account->fund->name ?? '—' }})</span>
                                    <span class="fw-medium">TSh {{ number_format($account->current_balance, 2) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted small text-center py-2">No welfare accounts</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Loan Summary --}}
            <div class="col-md-6">
                <div class="card vicoba-card h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>Loans</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Active Loans</span>
                            <span class="fw-bold">{{ $loanSummary['active_count'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Disbursed</span>
                            <span class="fw-medium">TSh {{ number_format($loanSummary['total_disbursed'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Paid</span>
                            <span class="fw-medium text-success">TSh {{ number_format($loanSummary['total_paid'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Outstanding Balance</span>
                            <span class="fw-bold text-danger">TSh {{ number_format($loanSummary['total_outstanding'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="text-muted">Completed Loans</span>
                            <span class="fw-medium">{{ $loanSummary['completed_count'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="card vicoba-card mt-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
            </div>
            <div class="card-body p-0">
                @if($recentTransactions->count() > 0)
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
                                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($tx->date)->format('d M Y') }}</td>
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
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No recent activity
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
