@extends('layouts.member')

@section('title', 'My Notifications - FinancePro VICOBA')
@section('page-title', 'My Notifications')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Notifications</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card vicoba-card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-bell me-2"></i>Notifications</h6>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-bell text-muted" style="font-size: 2rem;"></i>
                    </div>
                    <h6 class="text-muted fw-semibold">No notifications yet</h6>
                    <p class="text-muted small mb-0">
                        You will receive notifications here for important updates such as<br>
                        loan approvals, repayment reminders, and account activity.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
