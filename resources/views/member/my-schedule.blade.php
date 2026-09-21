@extends('layouts.member')

@section('title', 'Repayment Schedule - FinancePro VICOBA')
@section('page-title', 'Repayment Schedule')

@section('content')
@if($activeLoans->isEmpty())
    <div class="card vicoba-card">
        <div class="card-body text-center py-5">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-calendar3 text-muted" style="font-size: 2rem;"></i>
            </div>
            <h6 class="text-muted fw-semibold">No Active Loans</h6>
            <p class="text-muted small mb-3">You don't have any active loans with a repayment schedule.</p>
            <a href="{{ route('member.loans') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-cash-coin me-1"></i> View Loan Plans
            </a>
        </div>
    </div>
@elseif($activeLoans->count() === 1)
    {{-- Single loan: show schedule directly --}}
    @php $loan = $activeLoans->first(); @endphp
    <div class="card vicoba-card mb-4">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-calendar3 me-2"></i>Repayment Schedule &mdash; {{ $loan->loan_number }}
            </h6>
            <a href="{{ route('member.loans.show', $loan) }}" class="btn btn-outline-primary btn-sm">View Loan Details</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                        @forelse($loan->repaymentSchedule as $installment)
                            <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : ($installment->status->value === 'paid' ? 'table-success' : '') }}">
                                <td class="fw-medium">{{ $installment->installment_number }}</td>
                                <td>{{ $installment->due_date->format('d M Y') }}</td>
                                <td class="text-end">TSh {{ number_format($installment->principal_amount, 0) }}</td>
                                <td class="text-end">TSh {{ number_format($installment->interest_amount, 0) }}</td>
                                <td class="text-end fw-medium">TSh {{ number_format($installment->total_amount, 0) }}</td>
                                <td class="text-end text-success">TSh {{ number_format($installment->amount_paid, 0) }}</td>
                                <td class="text-end text-danger">TSh {{ number_format($installment->outstanding_amount, 0) }}</td>
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
                                <td class="text-end">TSh {{ number_format($loan->repaymentSchedule->sum('principal_amount'), 0) }}</td>
                                <td class="text-end">TSh {{ number_format($loan->repaymentSchedule->sum('interest_amount'), 0) }}</td>
                                <td class="text-end">TSh {{ number_format($loan->repaymentSchedule->sum('total_amount'), 0) }}</td>
                                <td class="text-end text-success">TSh {{ number_format($loan->repaymentSchedule->sum('amount_paid'), 0) }}</td>
                                <td class="text-end text-danger">TSh {{ number_format($loan->repaymentSchedule->sum('outstanding_amount'), 0) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@else
    {{-- Multiple loans: show each schedule --}}
    @foreach($activeLoans as $loan)
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-calendar3 me-2"></i>{{ $loan->loan_number }}
                    <span class="badge bg-primary ms-2">{{ $loan->loanPlan->name ?? '—' }}</span>
                </h6>
                <a href="{{ route('member.loans.schedule', $loan) }}" class="btn btn-outline-primary btn-sm">Full Schedule</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Due Date</th>
                                <th class="text-end">Total Due</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Outstanding</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->repaymentSchedule->take(5) as $installment)
                                <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : '' }}">
                                    <td>{{ $installment->installment_number }}</td>
                                    <td>{{ $installment->due_date->format('d M Y') }}</td>
                                    <td class="text-end">TSh {{ number_format($installment->total_amount, 0) }}</td>
                                    <td class="text-end text-success">TSh {{ number_format($installment->amount_paid, 0) }}</td>
                                    <td class="text-end text-danger">TSh {{ number_format($installment->outstanding_amount, 0) }}</td>
                                    <td><span class="badge bg-{{ $installment->status->color() }}">{{ $installment->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($loan->repaymentSchedule->count() > 5)
                    <div class="card-footer bg-transparent text-center">
                        <a href="{{ route('member.loans.schedule', $loan) }}" class="text-primary small">
                            View all {{ $loan->repaymentSchedule->count() }} installments &rarr;
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endif
@endsection
