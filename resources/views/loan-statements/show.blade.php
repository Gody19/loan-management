@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Loan Statement</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
                    <li class="breadcrumb-item active">Statement</li>
                </ol>
            </nav>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Print Statement
            </button>
        </div>
    </div>

    @include('layouts.components.alerts')

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">{{ $loan->organization->name ?? 'Organization' }}</h5>
                    <div class="mb-2"><strong>Loan Statement</strong></div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted">Loan Number:</div>
                        <div class="col-8 fw-semibold">{{ $loan->loan_number }}</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted">Member:</div>
                        <div class="col-8">{{ $loan->member->first_name ?? '' }} {{ $loan->member->last_name ?? '' }} ({{ $loan->member->member_number ?? '' }})</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted">Loan Plan:</div>
                        <div class="col-8">{{ $loan->loanPlan->name ?? 'N/A' }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row mb-1">
                        <div class="col-4 text-muted">Disbursement Date:</div>
                        <div class="col-8">{{ $loan->disbursement_date?->format('d M Y') ?? 'N/A' }}</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted">Maturity Date:</div>
                        <div class="col-8">{{ $loan->maturity_date?->format('d M Y') ?? 'N/A' }}</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted">Interest Method:</div>
                        <div class="col-8">{{ $loan->interest_method->label() }}</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted">Status:</div>
                        <div class="col-8">
                            <span class="badge bg-{{ $loan->status->color() }}">{{ $loan->status->label() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Loan Summary --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <small class="text-muted">Principal Amount</small>
                    <div class="fw-bold fs-5">TSh {{ number_format($loan->principal_amount, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <small class="text-muted">Total Interest</small>
                    <div class="fw-bold fs-5">TSh {{ number_format($loan->total_interest, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <small class="text-muted">Total Paid</small>
                    <div class="fw-bold fs-5">TSh {{ number_format($loan->amount_paid, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body text-center">
                    <small class="text-muted">Outstanding Balance</small>
                    <div class="fw-bold fs-5">TSh {{ number_format($loan->outstanding_balance, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment History --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Payment History</h5>
        </div>
        <div class="card-body p-0">
            @if($loan->repayments->isEmpty())
                <div class="text-center py-4">
                    <p class="text-muted mb-0">No payments recorded.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Repayment #</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Principal</th>
                                <th class="text-end">Interest</th>
                                <th class="text-end">Fees</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->repayments as $repayment)
                            <tr>
                                <td>{{ $repayment->payment_date->format('d M Y') }}</td>
                                <td>
                                    <a href="{{ route('loan-repayments.show', $repayment) }}" class="text-decoration-none">
                                        {{ $repayment->repayment_number }}
                                    </a>
                                </td>
                                <td class="text-end fw-semibold">TSh {{ number_format($repayment->amount, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->principal_portion, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->interest_portion, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->fee_portion, 2) }}</td>
                                <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $repayment->payment_method)) }}</span></td>
                                <td>
                                    <span class="badge bg-{{ $repayment->status->color() }}">{{ $repayment->status->label() }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2" class="text-end fw-bold">Total:</td>
                                <td class="text-end fw-bold">TSh {{ number_format($loan->repayments->where('status', 'posted')->sum('amount'), 2) }}</td>
                                <td class="text-end fw-bold">TSh {{ number_format($loan->repayments->where('status', 'posted')->sum('principal_portion'), 2) }}</td>
                                <td class="text-end fw-bold">TSh {{ number_format($loan->repayments->where('status', 'posted')->sum('interest_portion'), 2) }}</td>
                                <td class="text-end fw-bold">TSh {{ number_format($loan->repayments->where('status', 'posted')->sum('fee_portion'), 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Repayment Schedule --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Repayment Schedule</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Due Date</th>
                            <th class="text-end">Principal</th>
                            <th class="text-end">Interest</th>
                            <th class="text-end">Total Due</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Outstanding</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($loan->repaymentSchedule->sortBy('installment_number') as $installment)
                        <tr>
                            <td>{{ $installment->installment_number }}</td>
                            <td>{{ $installment->due_date->format('d M Y') }}</td>
                            <td class="text-end">TSh {{ number_format($installment->principal_amount, 2) }}</td>
                            <td class="text-end">TSh {{ number_format($installment->interest_amount, 2) }}</td>
                            <td class="text-end fw-semibold">TSh {{ number_format($installment->total_amount, 2) }}</td>
                            <td class="text-end text-success">TSh {{ number_format($installment->amount_paid, 2) }}</td>
                            <td class="text-end text-danger">TSh {{ number_format($installment->outstanding_amount, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $installment->status->color() }}">{{ $installment->status->label() }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
