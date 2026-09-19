@extends('layouts.member')

@section('title', 'My Disbursements - FinancePro VICOBA')
@section('page-title', 'Loan Disbursements')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Disbursements</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="card vicoba-card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-bank me-2"></i>Loan Disbursements</h6>
        <span class="badge bg-primary">{{ $disbursements->count() }} record(s)</span>
    </div>
    <div class="card-body">
        @if($disbursements->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p class="mb-0">No disbursements found.</p>
                <small>Disbursements will appear here once your loan is disbursed.</small>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Disbursement #</th>
                            <th>Loan #</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Fees</th>
                            <th class="text-end">Net Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($disbursements as $disbursement)
                            <tr>
                                <td><span class="fw-semibold">{{ $disbursement->disbursement_number }}</span></td>
                                <td>{{ $disbursement->loan?->loan_number ?? 'N/A' }}</td>
                                <td class="text-end">TSh {{ number_format($disbursement->amount, 2) }}</td>
                                <td class="text-end text-muted">
                                    TSh {{ number_format($disbursement->processing_fee + $disbursement->insurance_fee, 2) }}
                                </td>
                                <td class="text-end fw-semibold">TSh {{ number_format($disbursement->net_amount, 2) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $disbursement->disbursement_method)) }}</td>
                                <td>{{ $disbursement->disbursement_date->format('d M Y') }}</td>
                                <td>
                                    @if($disbursement->status->value === 'completed')
                                        <span class="badge bg-success">Completed</span>
                                    @elseif($disbursement->status->value === 'pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @elseif($disbursement->status->value === 'rejected')
                                        <span class="badge bg-danger">Rejected</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $disbursement->status->label() }}</span>
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
@endsection
