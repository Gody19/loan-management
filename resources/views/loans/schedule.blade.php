@extends('layouts.app')

@section('title', 'Repayment Schedule - ' . $loan->loan_number)

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Repayment Schedule',
        'subtitle' => $loan->loan_number . ' | ' . $loan->member->full_name ?? '—',
        'breadcrumb' => [
            ['label' => 'Loans', 'url' => route('loans.index')],
            ['label' => $loan->loan_number, 'url' => route('loans.show', $loan)],
            ['label' => 'Schedule'],
        ],
        'actions' => '<a href="' . route('loans.show', $loan) . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Loan
        </a>'
    ])
@endsection

@section('content')
    {{-- Loan Summary --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="text-muted small">Principal</div>
                    <div class="fw-bold fs-5">{{ number_format($loan->principal_amount, 0) }} TZS</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Total Interest</div>
                    <div class="fw-bold fs-5 text-primary">{{ number_format($loan->total_interest, 0) }} TZS</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Total Amount</div>
                    <div class="fw-bold fs-5">{{ number_format($loan->total_amount, 0) }} TZS</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Outstanding</div>
                    <div class="fw-bold fs-5 text-danger">{{ number_format($loan->outstanding_balance, 0) }} TZS</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Schedule Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2"></i>Repayment Schedule ({{ $loan->repaymentSchedule->count() }} installments)</h6>
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
                            <th>Days Overdue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loan->repaymentSchedule as $installment)
                            <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : ($installment->status->value === 'paid' ? 'table-success' : '') }}">
                                <td class="fw-medium">{{ $installment->installment_number }}</td>
                                <td>{{ $installment->due_date->format('d M Y') }}</td>
                                <td>{{ number_format($installment->principal_amount, 0) }} TZS</td>
                                <td>{{ number_format($installment->interest_amount, 0) }} TZS</td>
                                <td class="fw-medium">{{ number_format($installment->total_amount, 0) }} TZS</td>
                                <td class="text-success">{{ number_format($installment->amount_paid, 0) }} TZS</td>
                                <td class="text-danger">{{ number_format($installment->outstanding_amount, 0) }} TZS</td>
                                <td><span class="badge bg-{{ $installment->status->color() }}">{{ $installment->status->label() }}</span></td>
                                <td>
                                    @if($installment->days_overdue > 0)
                                        <span class="badge bg-danger">{{ $installment->days_overdue }} days</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
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
                            <td>{{ number_format($loan->repaymentSchedule->sum('principal_amount'), 0) }} TZS</td>
                            <td>{{ number_format($loan->repaymentSchedule->sum('interest_amount'), 0) }} TZS</td>
                            <td>{{ number_format($loan->repaymentSchedule->sum('total_amount'), 0) }} TZS</td>
                            <td class="text-success">{{ number_format($loan->repaymentSchedule->sum('amount_paid'), 0) }} TZS</td>
                            <td class="text-danger">{{ number_format($loan->repaymentSchedule->sum('outstanding_amount'), 0) }} TZS</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection
