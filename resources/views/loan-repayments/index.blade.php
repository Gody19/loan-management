@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Loan Repayments</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
                    <li class="breadcrumb-item active">Repayments</li>
                </ol>
            </nav>
        </div>
        <div>
            @can('create', \App\Models\LoanRepayment::class)
            <a href="{{ route('loan-repayments.create', $loan) }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Record Payment
            </a>
            @endcan
        </div>
    </div>

    @include('layouts.components.alerts')

    {{-- Loan Summary Card --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <small class="text-muted">Loan Number</small>
                    <div class="fw-semibold">{{ $loan->loan_number }}</div>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Member</small>
                    <div class="fw-semibold">{{ $loan->member->first_name ?? '' }} {{ $loan->member->last_name ?? '' }}</div>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Principal</small>
                    <div class="fw-semibold">TSh {{ number_format($loan->principal_amount, 2) }}</div>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Amount Paid</small>
                    <div class="fw-semibold text-success">TSh {{ number_format($loan->amount_paid, 2) }}</div>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Outstanding</small>
                    <div class="fw-semibold text-danger">TSh {{ number_format($loan->outstanding_balance, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Repayments Table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Payment History</h5>
            <span class="badge bg-primary">{{ $repayments->count() }} payments</span>
        </div>
        <div class="card-body p-0">
            @if($repayments->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No payments recorded yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Repayment #</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Principal</th>
                                <th class="text-end">Interest</th>
                                <th class="text-end">Fees</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($repayments as $repayment)
                            <tr>
                                <td>
                                    <a href="{{ route('loan-repayments.show', $repayment) }}" class="fw-semibold text-decoration-none">
                                        {{ $repayment->repayment_number }}
                                    </a>
                                </td>
                                <td>{{ $repayment->payment_date->format('d M Y') }}</td>
                                <td class="text-end fw-semibold">TSh {{ number_format($repayment->amount, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->principal_portion, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->interest_portion, 2) }}</td>
                                <td class="text-end">TSh {{ number_format($repayment->fee_portion, 2) }}</td>
                                <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $repayment->payment_method)) }}</span></td>
                                <td>
                                    <span class="badge bg-{{ $repayment->status->color() }}">
                                        {{ $repayment->status->label() }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('loan-repayments.show', $repayment) }}" class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($repayment->status->isReversible())
                                    @can('reverse', $repayment)
                                    <form action="{{ route('loan-repayments.reverse', $repayment) }}" method="POST" class="d-inline" data-confirm="Are you sure you want to reverse this payment?">
                                        @csrf
                                        <input type="hidden" name="reason" value="Reversal requested by {{ auth()->user()->fullname }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reverse">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                    @endcan
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
@endsection
