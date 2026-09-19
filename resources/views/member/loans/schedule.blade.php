@extends('layouts.member')

@section('title', 'Repayment Schedule - ' . $loan->loan_number)
@section('page-title', 'Repayment Schedule')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('member.loans') }}">My Loans</a></li>
            <li class="breadcrumb-item"><a href="{{ route('member.loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
            <li class="breadcrumb-item active">Schedule</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="text-muted small">Principal</div>
                        <div class="fw-bold fs-5">TSh {{ number_format($loan->principal_amount, 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Total Interest</div>
                        <div class="fw-bold fs-5 text-primary">TSh {{ number_format($loan->total_interest, 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Total Amount</div>
                        <div class="fw-bold fs-5">TSh {{ number_format($loan->total_amount, 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Outstanding</div>
                        <div class="fw-bold fs-5 text-danger">TSh {{ number_format($loan->outstanding_balance, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2"></i>Repayment Schedule ({{ $loan->repaymentSchedule->count() }} installments)</h6>
                <a href="{{ route('member.loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Loan
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Due Date</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Total Due</th>
                                <th>Amount Paid</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loan->repaymentSchedule as $installment)
                                <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : ($installment->status->value === 'paid' ? 'table-success' : '') }}">
                                    <td class="fw-medium">{{ $installment->installment_number }}</td>
                                    <td>{{ $installment->due_date->format('d M Y') }}</td>
                                    <td>TSh {{ number_format($installment->principal_amount, 0) }}</td>
                                    <td>TSh {{ number_format($installment->interest_amount, 0) }}</td>
                                    <td class="fw-medium">TSh {{ number_format($installment->total_amount, 0) }}</td>
                                    <td class="text-success">TSh {{ number_format($installment->amount_paid, 0) }}</td>
                                    <td class="text-danger">TSh {{ number_format($installment->outstanding_amount, 0) }}</td>
                                    <td><span class="badge bg-{{ $installment->status->color() }}">{{ $installment->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                        No schedule generated yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($loan->repaymentSchedule->count() > 0)
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="2">Totals</td>
                                <td>TSh {{ number_format($loan->repaymentSchedule->sum('principal_amount'), 0) }}</td>
                                <td>TSh {{ number_format($loan->repaymentSchedule->sum('interest_amount'), 0) }}</td>
                                <td>TSh {{ number_format($loan->repaymentSchedule->sum('total_amount'), 0) }}</td>
                                <td class="text-success">TSh {{ number_format($loan->repaymentSchedule->sum('amount_paid'), 0) }}</td>
                                <td class="text-danger">TSh {{ number_format($loan->repaymentSchedule->sum('outstanding_amount'), 0) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
