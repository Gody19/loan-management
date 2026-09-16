@extends('layouts.app')

@section('title', 'Disbursement ' . $disbursement->disbursement_number)

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Disbursement ' . $disbursement->disbursement_number,
        'subtitle' => $disbursement->loan->loan_number ?? '—' . ' | ' . $disbursement->status->label(),
        'breadcrumb' => [
            ['label' => 'Disbursements', 'url' => route('loan-disbursements.index')],
            ['label' => $disbursement->disbursement_number],
        ],
        'actions' => '<a href="' . route('loan-disbursements.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>'
        . ($disbursement->status->value === 'pending' ? '<form method="POST" action="' . route('loan-disbursements.confirm', $disbursement) . '" class="d-inline" data-confirm="Confirm this disbursement? Loan will be activated.">@csrf<button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Confirm</button></form>' : '')
        . ($disbursement->status->value === 'pending' ? '<form method="POST" action="' . route('loan-disbursements.reject', $disbursement) . '" class="d-inline">@csrf<div class="input-group input-group-sm" style="max-width:300px"><input type="text" name="reason" class="form-control" placeholder="Rejection reason..." required><button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i></button></div></form>' : '')
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-cash-stack text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $disbursement->disbursement_number }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-{{ $disbursement->status->color() }}">{{ $disbursement->status->label() }}</span>
                            <span class="text-muted">Loan: {{ $disbursement->loan->loan_number ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Net Amount</div>
                        <div class="fw-bold fs-4 text-success">{{ number_format($disbursement->net_amount, 0) }} TZS</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash me-2"></i>Disbursement Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Disbursement #</td><td class="fw-medium">{{ $disbursement->disbursement_number }}</td></tr>
                            <tr><td class="text-muted">Loan #</td><td class="fw-medium"><a href="{{ route('loans.show', $disbursement->loan) }}">{{ $disbursement->loan->loan_number ?? '—' }}</a></td></tr>
                            <tr><td class="text-muted">Member</td><td class="fw-medium">{{ $disbursement->loan->member->full_name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Amount</td><td class="fw-bold text-primary">{{ number_format($disbursement->amount, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Processing Fee</td><td class="fw-medium">{{ number_format($disbursement->processing_fee, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Insurance Fee</td><td class="fw-medium">{{ number_format($disbursement->insurance_fee, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Net Amount</td><td class="fw-bold text-success">{{ number_format($disbursement->net_amount, 0) }} TZS</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-gear me-2"></i>Processing Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Status</td><td><span class="badge bg-{{ $disbursement->status->color() }}">{{ $disbursement->status->label() }}</span></td></tr>
                            <tr><td class="text-muted">Method</td><td class="fw-medium">{{ ucfirst(str_replace('_', ' ', $disbursement->disbursement_method)) }}</td></tr>
                            <tr><td class="text-muted">Payment Method</td><td class="fw-medium">{{ $disbursement->paymentMethod->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Reference #</td><td class="fw-medium">{{ $disbursement->reference_number ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Disbursement Date</td><td class="fw-medium">{{ $disbursement->disbursement_date->format('d M Y') }}</td></tr>
                            <tr><td class="text-muted">Processed By</td><td class="fw-medium">{{ $disbursement->processor->fullname ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Notes</td><td class="fw-medium">{{ $disbursement->notes ?? '—' }}</td></tr>
                            @if($disbursement->rejection_reason)
                            <tr><td class="text-muted">Rejection Reason</td><td class="fw-medium text-danger">{{ $disbursement->rejection_reason }}</td></tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
