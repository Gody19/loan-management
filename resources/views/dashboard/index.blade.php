@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('breadcrumb')
    @include('layouts.components.breadcrumb', ['breadcrumbs' => ['Dashboard' => '']])
@endsection

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
                            {{ config('currency.symbol') }} {{ number_format($data['totalSavings']) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Active Loans --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <div class="stat-label">Active Loans</div>
                        <div class="stat-value">{{ number_format($data['totalLoans']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pending Applications --}}
    <div class="col-xl-3 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="stat-label">Pending Applications</div>
                        <div class="stat-value">{{ number_format($data['pendingApplications']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Second Row Stats --}}
<div class="row g-3 mb-4">

    {{-- Active Groups --}}
    <div class="col-xl-6 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <div>
                        <div class="stat-label">Active VICOBA Groups</div>
                        <div class="stat-value">{{ number_format($data['activeGroups']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Shares --}}
    <div class="col-xl-6 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Shares</div>
                        <div class="stat-value currency-value">
                            {{ config('currency.symbol') }} {{ number_format($data['totalShares']) }}
                        </div>
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
                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                    <p class="mb-0" style="font-size: 0.875rem;">No recent activity to display.</p>
                    <small>Activity will appear here as members interact with the system.</small>
                </div>
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
                    <a href="#" class="btn btn-outline-primary text-start vicoba-btn">
                        <i class="bi bi-person-plus me-2"></i> Register New Member
                    </a>
                    <a href="#" class="btn btn-outline-success text-start vicoba-btn">
                        <i class="bi bi-wallet2 me-2"></i> Record Savings
                    </a>
                    <a href="#" class="btn btn-outline-warning text-start vicoba-btn">
                        <i class="bi bi-cash-coin me-2"></i> New Loan Application
                    </a>
                    <a href="#" class="btn btn-outline-info text-start vicoba-btn">
                        <i class="bi bi-calendar-check me-2"></i> Schedule Meeting
                    </a>
                    <a href="#" class="btn btn-outline-secondary text-start vicoba-btn">
                        <i class="bi bi-clipboard-data me-2"></i> Generate Report
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection
