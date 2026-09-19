@extends('layouts.member')

@section('title', 'My Repayments - FinancePro VICOBA')
@section('page-title', 'My Repayments')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">My Repayments</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="stat-label">Total Payments</div>
                        <div class="stat-value">{{ $repayments->total() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="stat-label">Total Paid</div>
                        <div class="stat-value">TSh {{ number_format($repayments->sum('amount'), 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Payment History</h6>
        <span class="badge bg-primary">{{ $repayments->total() }} payments</span>
    </div>
    <div class="card-body p-0">
        @if($repayments->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-2">No payments recorded yet.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Repayment #</th>
                            <th>Loan</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Principal</th>
                            <th class="text-end">Interest</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($repayments as $repayment)
                            <tr>
                                <td>
                                    <a href="{{ route('member.repayments.show', $repayment) }}" class="fw-semibold text-decoration-none">
                                        {{ $repayment->repayment_number }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('member.loans.show', $repayment->loan) }}" class="text-decoration-none">
                                        {{ $repayment->loan->loan_number }}
                                    </a>
                                </td>
                                <td>{{ $repayment->payment_date->format('d M Y') }}</td>
                                <td class="text-end fw-semibold">TSh {{ number_format($repayment->amount, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->principal_portion, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->interest_portion, 2) }}</td>
                                <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $repayment->payment_method)) }}</span></td>
                                <td>
                                    <span class="badge bg-{{ $repayment->status->color() }}">
                                        {{ $repayment->status->label() }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('member.repayments.show', $repayment) }}" class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($repayments->hasPages())
                <div class="d-flex justify-content-center py-3">
                    {{ $repayments->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
