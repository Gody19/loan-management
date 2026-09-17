@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Loan Collections & Delinquency</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Collections</li>
                </ol>
            </nav>
        </div>
    </div>

    @include('layouts.components.alerts')

    {{-- PAR Summary Cards --}}
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <div class="text-danger mb-1">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                    <h3 class="mb-1">{{ number_format($collectionStats['par30']['par_percentage'], 1) }}%</h3>
                    <p class="text-muted mb-0">PAR 30+</p>
                    <small class="text-muted">TSh {{ number_format($collectionStats['par30']['delinquent_outstanding'], 0) }} at risk</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <div class="text-warning mb-1">
                        <i class="bi bi-exclamation-diamond fs-1"></i>
                    </div>
                    <h3 class="mb-1">{{ number_format($collectionStats['par60']['par_percentage'], 1) }}%</h3>
                    <p class="text-muted mb-0">PAR 60+</p>
                    <small class="text-muted">TSh {{ number_format($collectionStats['par60']['delinquent_outstanding'], 0) }} at risk</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-dark">
                <div class="card-body text-center">
                    <div class="text-dark mb-1">
                        <i class="bi bi-x-octagon fs-1"></i>
                    </div>
                    <h3 class="mb-1">{{ number_format($collectionStats['par90']['par_percentage'], 1) }}%</h3>
                    <p class="text-muted mb-0">PAR 90+</p>
                    <small class="text-muted">TSh {{ number_format($collectionStats['par90']['delinquent_outstanding'], 0) }} at risk</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Delinquent Loans --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Delinquent Loans</h5>
            <span class="badge bg-danger">{{ $delinquentLoans->count() }} loans</span>
        </div>
        <div class="card-body p-0">
            @if($delinquentLoans->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-check-circle fs-1 text-success"></i>
                    <p class="text-muted mt-2">No delinquent loans found. All payments are up to date.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Loan #</th>
                                <th>Member</th>
                                <th>Branch</th>
                                <th>Plan</th>
                                <th class="text-end">Outstanding</th>
                                <th class="text-center">Days Overdue</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($delinquentLoans as $loan)
                            @php
                                $daysOverdue = 0;
                                $oldestOverdue = $loan->repaymentSchedule->sortBy('due_date')->first();
                                if ($oldestOverdue) {
                                    $daysOverdue = \Carbon\Carbon::today()->diffInDays($oldestOverdue->due_date);
                                }
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('loans.show', $loan) }}" class="fw-semibold text-decoration-none">
                                        {{ $loan->loan_number }}
                                    </a>
                                </td>
                                <td>{{ $loan->member->first_name ?? '' }} {{ $loan->member->last_name ?? '' }}</td>
                                <td>{{ $loan->branch->name ?? 'N/A' }}</td>
                                <td>{{ $loan->loanPlan->name ?? 'N/A' }}</td>
                                <td class="text-end fw-semibold text-danger">TSh {{ number_format($loan->outstanding_balance, 2) }}</td>
                                <td class="text-center">
                                    @if($daysOverdue >= 90)
                                        <span class="badge bg-dark">{{ $daysOverdue }} days</span>
                                    @elseif($daysOverdue >= 60)
                                        <span class="badge bg-warning text-dark">{{ $daysOverdue }} days</span>
                                    @else
                                        <span class="badge bg-danger">{{ $daysOverdue }} days</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('loan-repayments.create', $loan) }}" class="btn btn-sm btn-success" title="Record Payment">
                                        <i class="bi bi-plus-circle"></i>
                                    </a>
                                    <a href="{{ route('loans.statement', $loan) }}" class="btn btn-sm btn-outline-info" title="Statement">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary" title="View Loan">
                                        <i class="bi bi-eye"></i>
                                    </a>
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
