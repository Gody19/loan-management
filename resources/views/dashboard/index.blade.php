@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Welcome back, ' . (auth()->user()->fullname ?? 'User'),
    ])
@endsection

@section('content')

{{-- Statistics Cards --}}
<div class="row g-3 mb-4">
    {{-- Total Members --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Members</div>
                        <div class="stat-value">{{ number_format($data['totalMembers']) }}</div>
                        <small class="text-muted">{{ $data['activeMembers'] }} active</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Savings --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Savings</div>
                        <div class="stat-value currency-value">
                            TSh {{ number_format($data['totalSavings']) }}
                        </div>
                        <small class="text-muted">This month: TSh {{ number_format($data['totalDepositThisMonth']) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Shares --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Shares</div>
                        <div class="stat-value">{{ number_format($data['totalShares']) }}</div>
                        <small class="text-muted">Value: TSh {{ number_format($data['totalShareValue']) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Welfare Balance --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-heart"></i>
                    </div>
                    <div>
                        <div class="stat-label">Welfare Balance</div>
                        <div class="stat-value currency-value">
                            TSh {{ number_format($data['totalWelfareBalance']) }}
                        </div>
                        <small class="text-muted">Contributions: TSh {{ number_format($data['totalWelfareContributions']) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Second Row Stats --}}
<div class="row g-3 mb-4">
    {{-- Active Groups --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <div>
                        <div class="stat-label">Active Groups</div>
                        <div class="stat-value">{{ number_format($data['activeGroups']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- New Members This Month --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <div class="stat-label">New Members</div>
                        <div class="stat-value">{{ number_format($data['newMembersThisMonth']) }}</div>
                        <small class="text-muted">This month</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Net Savings This Month --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <div class="stat-label">Net Savings</div>
                        <div class="stat-value">TSh {{ number_format($data['totalDepositThisMonth'] - $data['totalWithdrawalThisMonth']) }}</div>
                        <small class="text-muted">This month</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Withdrawals This Month --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                    <div>
                        <div class="stat-label">Withdrawals</div>
                        <div class="stat-value">TSh {{ number_format($data['totalWithdrawalThisMonth']) }}</div>
                        <small class="text-muted">This month</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Content Sections --}}
<div class="row g-3">

    {{-- Recent Activity --}}
    <div class="col-xl-8">
        <div class="card vicoba-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Recent Activity</h6>
            </div>
            <div class="card-body p-0">
                @if($data['recentActivity']->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Transaction</th>
                                    <th>Member</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['recentActivity'] as $activity)
                                    <tr>
                                        <td>
                                            <span class="badge bg-{{ $activity['color'] }}-subtle text-{{ $activity['color'] }}">
                                                <i class="bi {{ $activity['icon'] }} me-1"></i>
                                                {{ $activity['label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $activity['member'] }}</td>
                                        <td>TSh {{ number_format($activity['amount'], 2) }}</td>
                                        <td>{{ $activity['date']?->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                        <p class="mb-0" style="font-size: 0.875rem;">No recent activity to display.</p>
                        <small>Activity will appear here as members interact with the system.</small>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-xl-4">
        <div class="card vicoba-card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('members.create') }}" class="btn btn-outline-primary text-start vicoba-btn">
                        <i class="bi bi-person-plus me-2"></i> Register New Member
                    </a>
                    <a href="{{ route('savings-products.index') }}" class="btn btn-outline-success text-start vicoba-btn">
                        <i class="bi bi-wallet2 me-2"></i> View Savings Plans
                    </a>
                    <a href="{{ route('share-products.index') }}" class="btn btn-outline-warning text-start vicoba-btn">
                        <i class="bi bi-cash-stack me-2"></i> View Share Plans
                    </a>
                    <a href="{{ route('welfare-funds.index') }}" class="btn btn-outline-info text-start vicoba-btn">
                        <i class="bi bi-heart me-2"></i> View Welfare Funds
                    </a>
                    <a href="{{ route('members.index') }}" class="btn btn-outline-secondary text-start vicoba-btn">
                        <i class="bi bi-people me-2"></i> View All Members
                    </a>
                </div>
            </div>
        </div>

        {{-- Savings Summary --}}
        <div class="card vicoba-card mt-3">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Savings Summary</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Deposits</span>
                    <span class="text-success fw-bold">TSh {{ number_format($data['totalDepositThisMonth']) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Withdrawals</span>
                    <span class="text-danger fw-bold">TSh {{ number_format($data['totalWithdrawalThisMonth']) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">Net Savings</span>
                    <span class="text-primary fw-bold">TSh {{ number_format($data['totalDepositThisMonth'] - $data['totalWithdrawalThisMonth']) }}</span>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection
