@extends('layouts.member')

@section('title', 'Collections - FinancePro VICOBA')
@section('page-title', 'Loan Collections & Repayment Schedule')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Collections</li>
        </ol>
    </nav>
@endsection

@section('content')
@if($loanSummaries->isEmpty())
    <div class="card vicoba-card">
        <div class="card-body">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p class="mb-0">No active loans found.</p>
                <small>Repayment schedules will appear here once you have an active loan.</small>
            </div>
        </div>
    </div>
@else
    @foreach($loanSummaries as $summary)
        @php
            $loan = $summary['loan'];
            $hasOverdue = $summary['overdue_count'] > 0;
        @endphp
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-cash-coin me-2"></i>
                        {{ $loan->loan_number }}
                        <span class="badge bg-light text-dark ms-2">{{ $loan->loanPlan?->name ?? 'N/A' }}</span>
                    </h6>
                </div>
                <div>
                    @if($hasOverdue)
                        <span class="badge bg-danger">{{ $summary['overdue_count'] }} overdue</span>
                    @endif
                    <a href="{{ route('member.loans.schedule', $loan) }}" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="bi bi-calendar3 me-1"></i> Full Schedule
                    </a>
                </div>
            </div>
            <div class="card-body">
                {{-- Loan Summary --}}
                <div class="row mb-3">
                    <div class="col-md-3">
                        <small class="text-muted">Principal</small>
                        <div class="fw-semibold">TSh {{ number_format($loan->principal_amount, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Paid</small>
                        <div class="fw-semibold text-success">TSh {{ number_format($loan->amount_paid, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Outstanding</small>
                        <div class="fw-semibold text-danger">TSh {{ number_format($loan->outstanding_balance, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Next Payment</small>
                        @if($summary['next_due'])
                            <div class="fw-semibold">
                                TSh {{ number_format($summary['next_due']->total_amount - $summary['next_due']->amount_paid, 2) }}
                                <small class="text-muted">due {{ $summary['next_due']->due_date->format('d M Y') }}</small>
                            </div>
                        @else
                            <div class="text-success fw-semibold">Fully Paid</div>
                        @endif
                    </div>
                </div>

                {{-- Pending Installments --}}
                @if($summary['pending_installments']->isNotEmpty())
                    <h6 class="mb-2">Pending Installments</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summary['pending_installments'] as $installment)
                                    <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : '' }}">
                                        <td>{{ $installment->installment_number }}</td>
                                        <td>{{ $installment->due_date->format('d M Y') }}</td>
                                        <td class="text-end">TSh {{ number_format($installment->total_amount, 2) }}</td>
                                        <td class="text-end">TSh {{ number_format($installment->amount_paid, 2) }}</td>
                                        <td class="text-end fw-semibold">
                                            TSh {{ number_format($installment->total_amount - $installment->amount_paid, 2) }}
                                        </td>
                                        <td>
                                            @if($installment->status->value === 'overdue')
                                                <span class="badge bg-danger">Overdue</span>
                                            @elseif($installment->status->value === 'partial')
                                                <span class="badge bg-warning text-dark">Partial</span>
                                            @else
                                                <span class="badge bg-secondary">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($summary['total_pending'] > 0)
                        <div class="text-end mt-2">
                            <a href="{{ route('member.loans.repay', $loan) }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-cash me-1"></i> Make Payment
                            </a>
                        </div>
                    @endif
                @else
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle me-1"></i>
                        All installments for this loan are paid.
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endif
@endsection
