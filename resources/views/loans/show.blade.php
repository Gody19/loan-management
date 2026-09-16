@extends('layouts.app')

@section('title', 'Loan ' . $loan->loan_number)

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Loan ' . $loan->loan_number,
        'subtitle' => $loan->member->full_name ?? '—' . ' | ' . $loan->status->label(),
        'breadcrumb' => [
            ['label' => 'Loans', 'url' => route('loans.index')],
            ['label' => $loan->loan_number],
        ],
        'actions' => '<a href="' . route('loans.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>'
        . ($loan->status->value === 'pending_disbursement' ? '<form method="POST" action="' . route('loans.cancel', $loan) . '" class="d-inline" data-confirm="Cancel this loan?">@csrf<input type="hidden" name="reason" value="Cancelled by user"><button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i> Cancel</button></form>' : '')
        . ($loan->status->value === 'pending_disbursement' ? '<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#disbursementModal"><i class="bi bi-cash-stack me-1"></i> Disburse</button>' : '')
        . ($loan->status->value === 'active' ? '<a href="' . route('loans.schedule', $loan) . '" class="btn btn-info text-white"><i class="bi bi-calendar3 me-1"></i> Schedule</a>' : '')
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Summary Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-cash-coin text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $loan->loan_number }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-{{ $loan->status->color() }}">{{ $loan->status->label() }}</span>
                            <span class="text-muted">{{ $loan->loanPlan->name ?? '—' }}</span>
                            <span class="text-muted">{{ $loan->branch->name ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Outstanding Balance</div>
                        <div class="fw-bold fs-4 text-danger">{{ number_format($loan->outstanding_balance, 0) }} TZS</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- Loan Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Loan Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Loan Number</td><td class="fw-medium">{{ $loan->loan_number }}</td></tr>
                            <tr><td class="text-muted">Member</td><td class="fw-medium">{{ $loan->member->full_name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Member #</td><td class="fw-medium">{{ $loan->member->member_number ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Loan Plan</td><td class="fw-medium">{{ $loan->loanPlan->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Branch</td><td class="fw-medium">{{ $loan->branch->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Application #</td><td class="fw-medium">{{ $loan->loanApplication->application_number ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Disbursed By</td><td class="fw-medium">{{ $loan->disburser->fullname ?? '—' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Financial Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-calculator me-2"></i>Financial Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Principal Amount</td><td class="fw-bold text-primary">{{ number_format($loan->principal_amount, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Interest Rate</td><td class="fw-medium">{{ number_format($loan->interest_rate, 2) }}% (annual)</td></tr>
                            <tr><td class="text-muted">Interest Method</td><td class="fw-medium">{{ $loan->interest_method->label() }}</td></tr>
                            <tr><td class="text-muted">Term</td><td class="fw-medium">{{ $loan->term_months }} months</td></tr>
                            <tr><td class="text-muted">Frequency</td><td class="fw-medium">{{ $loan->repayment_frequency->label() }}</td></tr>
                            <tr><td class="text-muted">Total Interest</td><td class="fw-medium">{{ number_format($loan->total_interest, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Total Amount</td><td class="fw-bold">{{ number_format($loan->total_amount, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Processing Fee</td><td class="fw-medium">{{ number_format($loan->processing_fee, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Insurance Fee</td><td class="fw-medium">{{ number_format($loan->insurance_fee, 0) }} TZS</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Payment Progress --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-graph-up me-2"></i>Payment Progress</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $progress = $loan->total_amount > 0 ? round(($loan->amount_paid / $loan->total_amount) * 100, 1) : 0;
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Amount Paid</span>
                                <span class="fw-medium">{{ number_format($loan->amount_paid, 0) }} TZS ({{ $progress }}%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Amount Paid</td><td class="fw-medium text-success">{{ number_format($loan->amount_paid, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Outstanding</td><td class="fw-medium text-danger">{{ number_format($loan->outstanding_balance, 0) }} TZS</td></tr>
                            <tr><td class="text-muted">Installments Paid</td><td class="fw-medium">{{ $loan->installments_paid }} / {{ $loan->total_installments }}</td></tr>
                            <tr><td class="text-muted">Next Payment</td><td class="fw-medium">{{ $loan->next_payment_date?->format('d M Y') ?? '—' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-2"></i>Timeline</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Created</td><td class="fw-medium">{{ $loan->created_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Disbursement Date</td><td class="fw-medium">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Maturity Date</td><td class="fw-medium">{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Grace Period</td><td class="fw-medium">{{ $loan->grace_period }} days</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Disbursement Records --}}
            @if($loan->disbursements->count() > 0)
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2"></i>Disbursement Records</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Disbursement #</th>
                                        <th>Amount</th>
                                        <th>Fees</th>
                                        <th>Net Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loan->disbursements as $d)
                                        <tr>
                                            <td><span class="badge bg-info">{{ $d->disbursement_number }}</span></td>
                                            <td>{{ number_format($d->amount, 0) }} TZS</td>
                                            <td>{{ number_format($d->processing_fee + $d->insurance_fee, 0) }} TZS</td>
                                            <td class="fw-medium">{{ number_format($d->net_amount, 0) }} TZS</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $d->disbursement_method)) }}</td>
                                            <td><span class="badge bg-{{ $d->status->color() }}">{{ $d->status->label() }}</span></td>
                                            <td>{{ $d->disbursement_date->format('d M Y') }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('loan-disbursements.show', $d) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- Disbursement Modal --}}
@if($loan->status->value === 'pending_disbursement')
<div class="modal fade" id="disbursementModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loan-disbursements.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Disbursement Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="loan_id" value="{{ $loan->id }}">
                    <div class="mb-3">
                        <label class="form-label">Amount (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                               max="{{ $loan->principal_amount }}" value="{{ $loan->principal_amount }}" required>
                        <small class="text-muted">Maximum: {{ number_format($loan->principal_amount, 0) }} TZS</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Disbursement Method <span class="text-danger">*</span></label>
                        <select name="disbursement_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method_id" class="form-select">
                            <option value="">Select Payment Method</option>
                            @foreach(\App\Models\PaymentMethod::where('organization_id', $loan->organization_id)->where('status', 'active')->get() as $pm)
                                <option value="{{ $pm->id }}">{{ $pm->name }} ({{ $pm->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control" placeholder="Transaction reference...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong>Fees:</strong> Processing: {{ number_format($loan->processing_fee, 0) }} TZS | Insurance: {{ number_format($loan->insurance_fee, 0) }} TZS<br>
                        <strong>Net Amount:</strong> {{ number_format($loan->principal_amount - $loan->processing_fee - $loan->insurance_fee, 0) }} TZS
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Disbursement</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
