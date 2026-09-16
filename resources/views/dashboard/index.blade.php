@extends('layouts.app')

@section('title', $title . ' - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $title,
        'subtitle' => $welcome . ' | ' . $scope_label,
    ])
@endsection

@section('content')

{{-- ===== SUPER ADMIN DASHBOARD ===== --}}
@if($dashboard_type === 'super_admin')

    {{-- Platform Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-building"></i>
                        </div>
                        <div>
                            <div class="stat-label">Organizations</div>
                            <div class="stat-value">{{ number_format($widgets['organizations']['total']) }}</div>
                            <small class="text-muted">{{ $widgets['organizations']['active'] }} active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                        <div>
                            <div class="stat-label">Branches</div>
                            <div class="stat-value">{{ number_format($widgets['branches']['total']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="stat-label">Groups</div>
                            <div class="stat-value">{{ number_format($widgets['groups']['total']) }}</div>
                            <small class="text-muted">{{ $widgets['groups']['active'] }} active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div>
                            <div class="stat-label">Members</div>
                            <div class="stat-value">{{ number_format($widgets['members']['total']) }}</div>
                            <small class="text-muted">{{ $widgets['members']['active'] }} active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div>
                            <div class="stat-label">Total Savings</div>
                            <div class="stat-value">TSh {{ number_format($widgets['savings']['total_balance']) }}</div>
                            <small class="text-muted">{{ $widgets['savings']['accounts'] }} accounts</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <div class="stat-label">Total Shares</div>
                            <div class="stat-value">{{ number_format($widgets['shares']['total_shares']) }}</div>
                            <small class="text-muted">Value: TSh {{ number_format($widgets['shares']['total_value']) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-heart"></i>
                        </div>
                        <div>
                            <div class="stat-label">Welfare Balance</div>
                            <div class="stat-value">TSh {{ number_format($widgets['welfare']['total_balance']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div>
                            <div class="stat-label">Loan Applications</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['applications']) }}</div>
                            <small class="text-muted">{{ $widgets['loans']['pending'] }} pending</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <div class="stat-label">Users</div>
                            <div class="stat-value">{{ number_format($widgets['users']['total']) }}</div>
                            <small class="text-muted">{{ $widgets['users']['active'] }} active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <div class="stat-label">Pending Applications</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['pending']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-search"></i>
                        </div>
                        <div>
                            <div class="stat-label">Under Review</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['under_review']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div>
                            <div class="stat-label">Approved</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['approved']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            @include('dashboard._recent_activity')
        </div>
        <div class="col-xl-4">
            <div class="card vicoba-card">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Quick Actions</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @can('organization.create')
                        <a href="{{ route('organizations.create') }}" class="btn btn-outline-primary text-start"><i class="bi bi-building me-2"></i> Create Organization</a>
                        @endcan
                        @can('user.view')
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary text-start"><i class="bi bi-people-fill me-2"></i> Manage Users</a>
                        @endcan
                        @can('member.view')
                        <a href="{{ route('members.index') }}" class="btn btn-outline-info text-start"><i class="bi bi-people me-2"></i> View Members</a>
                        @endcan
                        @can('loan_application.view')
                        <a href="{{ route('loan-applications.index') }}" class="btn btn-outline-warning text-start"><i class="bi bi-cash-coin me-2"></i> Loan Applications</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- ===== ORGANIZATION ADMIN DASHBOARD ===== --}}
@elseif($dashboard_type === 'org_admin')

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-diagram-3"></i></div>
                        <div>
                            <div class="stat-label">Branches</div>
                            <div class="stat-value">{{ number_format($widgets['organization']['branches']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-people"></i></div>
                        <div>
                            <div class="stat-label">Groups</div>
                            <div class="stat-value">{{ number_format($widgets['organization']['groups']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-person-plus"></i></div>
                        <div>
                            <div class="stat-label">Members</div>
                            <div class="stat-value">{{ number_format($widgets['members']['total']) }}</div>
                            <small class="text-muted">{{ $widgets['members']['active'] }} active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-person-x"></i></div>
                        <div>
                            <div class="stat-label">Pending Members</div>
                            <div class="stat-value">{{ number_format($widgets['members']['pending']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="stat-label">Savings</div>
                            <div class="stat-value">TSh {{ number_format($widgets['savings']['total_balance']) }}</div>
                            <small class="text-muted">{{ $widgets['savings']['accounts'] }} accounts</small>
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
                            <div class="stat-label">Shares</div>
                            <div class="stat-value">{{ number_format($widgets['shares']['total_shares']) }}</div>
                            <small class="text-muted">Value: TSh {{ number_format($widgets['shares']['total_value']) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-heart"></i></div>
                        <div>
                            <div class="stat-label">Welfare</div>
                            <div class="stat-value">TSh {{ number_format($widgets['welfare']['total_balance']) }}</div>
                            <small class="text-muted">{{ $widgets['welfare']['accounts'] }} accounts</small>
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
                            <div class="stat-label">Loan Plans</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['plans']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-file-earmark-text"></i></div>
                        <div>
                            <div class="stat-label">Applications</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['applications']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="stat-label">Pending Review</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['pending']) }}</div>
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
                            <div class="stat-label">Approved</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['approved']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-x-circle"></i></div>
                        <div>
                            <div class="stat-label">Rejected</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['rejected']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">@include('dashboard._recent_activity')</div>
        <div class="col-xl-4">
            <div class="card vicoba-card">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Quick Actions</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @can('member.create')
                        <a href="{{ route('members.create') }}" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus me-2"></i> Add Member</a>
                        @endcan
                        @can('branch.create')
                        <a href="{{ route('branches.create') }}" class="btn btn-outline-secondary text-start"><i class="bi bi-diagram-3 me-2"></i> Add Branch</a>
                        @endcan
                        @can('group.create')
                        <a href="{{ route('vicoba-groups.create') }}" class="btn btn-outline-info text-start"><i class="bi bi-people me-2"></i> Add VICOBA Group</a>
                        @endcan
                        @can('loan_plan.create')
                        <a href="{{ route('loan-plans.create') }}" class="btn btn-outline-warning text-start"><i class="bi bi-cash-coin me-2"></i> Create Loan Plan</a>
                        @endcan
                        @can('loan_application.view')
                        <a href="{{ route('loan-applications.index') }}" class="btn btn-outline-success text-start"><i class="bi bi-file-earmark-text me-2"></i> View Applications</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- ===== BRANCH MANAGER DASHBOARD ===== --}}
@elseif($dashboard_type === 'branch_manager')

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-people"></i></div>
                        <div>
                            <div class="stat-label">Groups</div>
                            <div class="stat-value">{{ number_format($widgets['branch']['groups']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-person-plus"></i></div>
                        <div>
                            <div class="stat-label">Members</div>
                            <div class="stat-value">{{ number_format($widgets['members']['total']) }}</div>
                            <small class="text-muted">{{ $widgets['members']['active'] }} active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="stat-label">Savings</div>
                            <div class="stat-value">TSh {{ number_format($widgets['savings']['total_balance']) }}</div>
                            <small class="text-muted">{{ $widgets['savings']['accounts'] }} accounts</small>
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
                            <div class="stat-label">Shares</div>
                            <div class="stat-value">{{ number_format($widgets['shares']['total_shares']) }}</div>
                            <small class="text-muted">Value: TSh {{ number_format($widgets['shares']['total_value']) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-heart"></i></div>
                        <div>
                            <div class="stat-label">Welfare</div>
                            <div class="stat-value">TSh {{ number_format($widgets['welfare']['total_balance']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-file-earmark-text"></i></div>
                        <div>
                            <div class="stat-label">Applications</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['applications']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="stat-label">Pending Review</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['pending']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-search"></i></div>
                        <div>
                            <div class="stat-label">Under Review</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['under_review']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">@include('dashboard._recent_activity')</div>
        <div class="col-xl-4">
            <div class="card vicoba-card">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Quick Actions</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @can('member.create')
                        <a href="{{ route('members.create') }}" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus me-2"></i> Add Member</a>
                        @endcan
                        @can('member.view')
                        <a href="{{ route('members.index') }}" class="btn btn-outline-secondary text-start"><i class="bi bi-people me-2"></i> View Members</a>
                        @endcan
                        @can('loan_application.view')
                        <a href="{{ route('loan-applications.index') }}" class="btn btn-outline-warning text-start"><i class="bi bi-file-earmark-text me-2"></i> View Applications</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- ===== STAFF DASHBOARD ===== --}}
@elseif($dashboard_type === 'staff')

    <div class="row g-3 mb-4">
        @if(isset($widgets['members']))
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-person-plus"></i></div>
                        <div>
                            <div class="stat-label">Members</div>
                            <div class="stat-value">{{ number_format($widgets['members']['total']) }}</div>
                            <small class="text-muted">{{ $widgets['members']['active'] }} active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @if(isset($widgets['savings']))
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="stat-label">Savings</div>
                            <div class="stat-value">TSh {{ number_format($widgets['savings']['total_balance']) }}</div>
                            <small class="text-muted">{{ $widgets['savings']['accounts'] }} accounts</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @if(isset($widgets['shares']))
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-cash-stack"></i></div>
                        <div>
                            <div class="stat-label">Shares</div>
                            <div class="stat-value">{{ number_format($widgets['shares']['total_shares']) }}</div>
                            <small class="text-muted">Value: TSh {{ number_format($widgets['shares']['total_value']) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @if(isset($widgets['welfare']))
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-heart"></i></div>
                        <div>
                            <div class="stat-label">Welfare</div>
                            <div class="stat-value">TSh {{ number_format($widgets['welfare']['total_balance']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if(isset($widgets['loans']))
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-file-earmark-text"></i></div>
                        <div>
                            <div class="stat-label">Applications</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['applications']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="stat-label">Pending</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['pending']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-search"></i></div>
                        <div>
                            <div class="stat-label">Under Review</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['under_review']) }}</div>
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
                            <div class="stat-label">Approved</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['approved']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-xl-8">@include('dashboard._recent_activity')</div>
        <div class="col-xl-4">
            <div class="card vicoba-card">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Quick Actions</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @can('member.create')
                        <a href="{{ route('members.create') }}" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus me-2"></i> Add Member</a>
                        @endcan
                        @can('loan_application.create')
                        <a href="{{ route('loan-applications.create') }}" class="btn btn-outline-warning text-start"><i class="bi bi-cash-coin me-2"></i> New Loan Application</a>
                        @endcan
                        @can('loan_application.view')
                        <a href="{{ route('loan-applications.index') }}" class="btn btn-outline-success text-start"><i class="bi bi-file-earmark-text me-2"></i> View Applications</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- ===== MEMBER DASHBOARD ===== --}}
@elseif($dashboard_type === 'member')

    <div class="row g-3 mb-4">
        @if(isset($widgets['savings']))
        <div class="col-xl-4 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="stat-label">My Savings</div>
                            <div class="stat-value">TSh {{ number_format($widgets['savings']['balance']) }}</div>
                            <small class="text-muted">{{ $widgets['savings']['accounts'] }} account(s)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @if(isset($widgets['shares']))
        <div class="col-xl-4 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-cash-stack"></i></div>
                        <div>
                            <div class="stat-label">My Shares</div>
                            <div class="stat-value">{{ number_format($widgets['shares']['total_shares']) }}</div>
                            <small class="text-muted">Value: TSh {{ number_format($widgets['shares']['total_value']) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @if(isset($widgets['loans']))
        <div class="col-xl-4 col-md-6">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-cash-coin"></i></div>
                        <div>
                            <div class="stat-label">My Loans</div>
                            <div class="stat-value">{{ number_format($widgets['loans']['applications']) }}</div>
                            <small class="text-muted">{{ $widgets['loans']['pending'] }} pending | {{ $widgets['loans']['approved'] }} approved</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if(!$widgets)
    <div class="card vicoba-card">
        <div class="card-body text-center py-5">
            <i class="bi bi-person-circle fs-1 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">Welcome to Your Dashboard</h5>
            <p class="text-muted mb-0">Your member profile is being set up. Contact your group officer for assistance.</p>
        </div>
    </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card vicoba-card">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Quick Actions</h6></div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        @can('savings.view')
                        <a href="{{ route('savings-accounts.index') }}" class="btn btn-outline-success"><i class="bi bi-wallet2 me-1"></i> My Savings</a>
                        @endcan
                        @can('shares.view')
                        <a href="{{ route('share-accounts.index') }}" class="btn btn-outline-warning"><i class="bi bi-cash-stack me-1"></i> My Shares</a>
                        @endcan
                        @can('loan_application.view')
                        <a href="{{ route('loan-applications.index') }}" class="btn btn-outline-primary"><i class="bi bi-cash-coin me-1"></i> My Loans</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

@endif

@endsection
