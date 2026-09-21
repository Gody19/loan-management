@extends('layouts.member')

@section('title', 'Select Loan to Pay - FinancePro VICOBA')
@section('page-title', 'Make a Payment')


@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-cash-stack text-white fs-4"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold">Select Loan to Pay</h4>
                        <p class="text-muted mb-0">Choose which active loan you want to make a payment towards.</p>
                    </div>
                </div>
            </div>
        </div>

        @if($loans->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                    <h5 class="text-muted">No Active Loans</h5>
                    <p class="text-muted">You don't have any active loans to make payments on.</p>
                    <a href="{{ route('member.loans') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Browse Loan Plans
                    </a>
                </div>
            </div>
        @else
            @foreach($loans as $loan)
                @php
                    $overdueCount = $loan->repaymentSchedule()->where('status', 'overdue')->count();
                    $overdueAmount = $loan->repaymentSchedule()->where('status', 'overdue')->sum('outstanding_amount');
                    $nextDue = $loan->repaymentSchedule()->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('due_date')->first();
                    $progress = $loan->total_amount > 0 ? round(($loan->amount_paid / $loan->total_amount) * 100, 1) : 0;
                @endphp
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center mb-3 mb-md-0">
                                <div class="bg-{{ $loan->status->value === 'pending_disbursement' ? 'warning' : 'primary' }} rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                    <i class="bi bi-cash-coin text-white fs-5"></i>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="fw-bold fs-5">{{ $loan->loan_number }}</div>
                                <div class="text-muted small">
                                    {{ $loan->loanPlan->name ?? '—' }} &middot; {{ $loan->branch->name ?? '—' }}
                                </div>
                                <div class="mt-1">
                                    <span class="badge bg-{{ $loan->status->color() }}">{{ $loan->status->label() }}</span>
                                    @if($overdueCount > 0)
                                        <span class="badge bg-danger ms-1">{{ $overdueCount }} overdue</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="text-muted small">Principal</div>
                                        <div class="fw-semibold small">{{ number_format($loan->principal_amount, 0) }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small">Paid</div>
                                        <div class="fw-semibold small text-success">{{ number_format($loan->amount_paid, 0) }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small">Outstanding</div>
                                        <div class="fw-semibold small text-danger">{{ number_format($loan->outstanding_balance, 0) }}</div>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                                </div>
                                <div class="text-muted small text-center mt-1">{{ $progress }}% paid</div>
                            </div>
                            <div class="col-md-3 text-center">
                                @if($nextDue)
                                    <div class="text-muted small">Next Payment Due</div>
                                    <div class="fw-semibold {{ \Carbon\Carbon::parse($nextDue->due_date)->isPast() ? 'text-danger' : '' }}">
                                        {{ \Carbon\Carbon::parse($nextDue->due_date)->format('d M Y') }}
                                    </div>
                                    <div class="text-muted small">TSh {{ number_format($nextDue->outstanding_amount, 0) }}</div>
                                @else
                                    <div class="text-muted small">No pending installments</div>
                                @endif
                            </div>
                            <div class="col-md-2 text-end">
                                @if(in_array($loan->status->value, ['active', 'pending_disbursement']) && (float) $loan->outstanding_balance > 0)
                                    <a href="{{ route('member.loans.repay', $loan) }}" class="btn btn-success">
                                        <i class="bi bi-cash me-1"></i> Pay
                                    </a>
                                @elseif((float) $loan->outstanding_balance <= 0)
                                    <span class="badge bg-success fs-6">Fully Paid</span>
                                @else
                                    <span class="badge bg-secondary">Not Available</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="text-muted small text-center mt-3">
                Showing {{ $loans->count() }} active loan(s)
            </div>
        @endif
    </div>
</div>
@endsection
