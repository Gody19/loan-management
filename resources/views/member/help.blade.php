@extends('layouts.member')

@section('title', 'Help & Support - FinancePro VICOBA')
@section('page-title', 'Help & Support')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Help & Support</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">

        {{-- How to Apply for a Loan --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>How to Apply for a Loan</h6>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li class="mb-2">Navigate to <strong>Loans &rarr; My Loans</strong> to view available loan plans.</li>
                    <li class="mb-2">Click <strong>"Apply Now"</strong> on the loan plan that suits your needs.</li>
                    <li class="mb-2">Fill in the requested amount, purpose, and repayment term.</li>
                    <li class="mb-2">Add guarantors if the loan plan requires them.</li>
                    <li class="mb-2">Review your application and click <strong>"Submit"</strong>.</li>
                    <li class="mb-0">Track your application status under <strong>Loan Applications</strong>.</li>
                </ol>
            </div>
        </div>

        {{-- How Repayment Works --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-credit-card me-2 text-success"></i>How Repayment Works</h6>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li class="mb-2">Once your loan is approved and disbursed, a <strong>Repayment Schedule</strong> is automatically generated.</li>
                    <li class="mb-2">Each installment has a due date, principal amount, and interest amount.</li>
                    <li class="mb-2">You can make payments via <strong>Loans &rarr; My Loans &rarr; Repay</strong>.</li>
                    <li class="mb-2">Select your payment method and enter the amount to pay.</li>
                    <li class="mb-0">Your outstanding balance and schedule will be updated automatically.</li>
                </ol>
            </div>
        </div>

        {{-- Understanding Repayment Schedules --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2 text-warning"></i>Understanding Your Repayment Schedule</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Your repayment schedule shows each installment you need to pay:</p>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th>Meaning</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-secondary">Pending</span></td>
                                <td>Not yet due. Payment is expected on or before the due date.</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">Partial</span></td>
                                <td>Partially paid. You still have an outstanding amount for this installment.</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">Paid</span></td>
                                <td>Fully paid. No further action required for this installment.</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-danger">Overdue</span></td>
                                <td>Past the due date. Please pay immediately to avoid penalties.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- How Guarantor Requests Work --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-check me-2 text-info"></i>How Guarantor Requests Work</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2"><strong>When someone requests you as a guarantor:</strong></p>
                <ol class="small mb-3">
                    <li class="mb-1">You will receive a notification under <strong>Guarantor Requests</strong>.</li>
                    <li class="mb-1">Review the loan details and the applicant's information.</li>
                    <li class="mb-1">Choose to <strong>Accept</strong> or <strong>Reject</strong> the request.</li>
                </ol>
                <p class="small text-muted mb-0"><strong>When you apply for a loan that requires a guarantor:</strong></p>
                <ol class="small mb-0">
                    <li class="mb-1">Add guarantors during the loan application process.</li>
                    <li class="mb-1">The guarantor will receive a notification and must approve your request.</li>
                    <li class="mb-0">The loan cannot be approved until all required guarantors have accepted.</li>
                </ol>
            </div>
        </div>

        {{-- Contact Support --}}
        <div class="card vicoba-card">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-headset me-2 text-primary"></i>Contact Support</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Group Secretary</div>
                        <div class="fw-medium small">{{ $member->vicobaGroup->secretary_name ?? 'Not assigned' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">VICOBA Group</div>
                        <div class="fw-medium small">{{ $member->vicobaGroup->name ?? 'Not assigned' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Branch</div>
                        <div class="fw-medium small">{{ $member->branch->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Organization</div>
                        <div class="fw-medium small">{{ $member->organization->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
