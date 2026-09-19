@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Loan Accounts — {{ $member->full_name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loan-repayments-collection.index') }}">Record Payment</a></li>
                    <li class="breadcrumb-item active">{{ $member->member_number }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('loan-repayments-collection.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Search
        </a>
    </div>

    @include('layouts.components.alerts')

    {{-- Member Info --}}
    <div class="row mb-4">
        <div class="col-lg-10 col-xl-9">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">Member Number</small>
                            <div class="fw-semibold">{{ $member->member_number }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Full Name</small>
                            <div class="fw-semibold">{{ $member->full_name }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Phone</small>
                            <div class="fw-semibold">{{ $member->phone ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">VICOBA Group</small>
                            <div class="fw-semibold">{{ $member->vicobaGroup?->name ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Loans --}}
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Loan Accounts ({{ $loans->count() }})</h5>
                </div>
                <div class="card-body">
                    @if($loans->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            <p>No active loans found for this member.</p>
                            <a href="{{ route('loan-repayments-collection.index') }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-arrow-left me-1"></i> Search Another Member
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Loan #</th>
                                        <th>Loan Plan</th>
                                        <th class="text-end">Principal</th>
                                        <th class="text-end">Outstanding</th>
                                        <th class="text-end">Paid</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loans as $loan)
                                        <tr>
                                            <td>
                                                <span class="fw-semibold">{{ $loan->loan_number }}</span>
                                            </td>
                                            <td>{{ $loan->loanPlan?->name ?? 'N/A' }}</td>
                                            <td class="text-end">TSh {{ number_format($loan->principal_amount, 2) }}</td>
                                            <td class="text-end">
                                                <span class="text-danger fw-semibold">
                                                    TSh {{ number_format($loan->outstanding_balance, 2) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-success">
                                                    TSh {{ number_format($loan->amount_paid, 2) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($loan->status->value === 'active')
                                                    <span class="badge bg-success">Active</span>
                                                @elseif($loan->status->value === 'pending_disbursement')
                                                    <span class="badge bg-warning text-dark">Pending Disbursement</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $loan->status->label() }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if($loan->status->value === 'active' && $loan->outstanding_balance > 0)
                                                    <a href="{{ route('loan-repayments-collection.create-repayment', $loan) }}"
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-cash me-1"></i> Record Payment
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
