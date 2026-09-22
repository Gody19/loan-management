@extends('layouts.app')

@section('title', 'Loan Plan')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $loanPlan->name,
        'subtitle' => 'Plan Code: ' . $loanPlan->code . ' | ' . $loanPlan->status->label(),
        'breadcrumb' => [
            ['label' => 'Loan Plans', 'url' => route('loan-plans.index')],
            ['label' => $loanPlan->name],
        ],
        'actions' => '<a href="' . route('loan-plans.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <a href="' . route('loan-plans.edit', $loanPlan) . '" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>'
    ])
@endsection

@section('content')
<div class="row">
    <div class="col-12">

        {{-- Plan Summary --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-cash-coin text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $loanPlan->name }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-primary">{{ $loanPlan->code }}</span>
                            <span class="badge bg-{{ $loanPlan->status->color() }}">{{ $loanPlan->status->label() }}</span>
                            <span class="badge bg-info">{{ $loanPlan->loan_purpose->label() }}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $loanPlan->organization->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- Plan Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Plan Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Name</td><td class="fw-medium">{{ $loanPlan->name }}</td></tr>
                            <tr><td class="text-muted">Code</td><td class="fw-medium">{{ $loanPlan->code }}</td></tr>
                            <tr><td class="text-muted">Organization</td><td class="fw-medium">{{ $loanPlan->organization->name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Purpose</td><td class="fw-medium">{{ $loanPlan->loan_purpose->label() }}</td></tr>
                            <tr><td class="text-muted">Description</td><td class="fw-medium">{{ $loanPlan->description ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $loanPlan->status->color() }}">{{ $loanPlan->status->label() }}</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Amount & Interest --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-currency-dollar me-2"></i>Amount & Interest</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Minimum Amount</td><td class="fw-medium">{{ number_format($loanPlan->minimum_amount, 2) }} TZS</td></tr>
                            <tr><td class="text-muted">Maximum Amount</td><td class="fw-medium">{{ number_format($loanPlan->maximum_amount, 2) }} TZS</td></tr>
                            <tr><td class="text-muted">Interest Rate</td><td class="fw-medium">{{ number_format($loanPlan->interest_rate, 1) }}%</td></tr>
                            <tr><td class="text-muted">Interest Method</td><td class="fw-medium">{{ $loanPlan->interest_method->label() }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Term & Repayment --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-range me-2"></i>Term & Repayment</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Minimum Term</td><td class="fw-medium">{{ $loanPlan->minimum_term }} months</td></tr>
                            <tr><td class="text-muted">Maximum Term</td><td class="fw-medium">{{ $loanPlan->maximum_term }} months</td></tr>
                            <tr><td class="text-muted">Repayment Frequency</td><td class="fw-medium">{{ $loanPlan->repayment_frequency->label() }}</td></tr>
                            <tr><td class="text-muted">Grace Period</td><td class="fw-medium">{{ $loanPlan->grace_period }} days</td></tr>
                            <tr><td class="text-muted">Max Active Loans</td><td class="fw-medium">{{ $loanPlan->maximum_active_loans }}</td></tr>
                            <tr><td class="text-muted">Late Payment Allowed</td><td class="fw-medium">{{ $loanPlan->late_payment_allowed ? 'Yes' : 'No' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Requirements --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-check me-2"></i>Requirements</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Requires Guarantor</td><td class="fw-medium">{{ $loanPlan->requires_guarantor ? 'Yes' : 'No' }}</td></tr>
                            <tr><td class="text-muted">Minimum Guarantors</td><td class="fw-medium">{{ $loanPlan->minimum_guarantors }}</td></tr>
                            <tr><td class="text-muted">Requires Collateral</td><td class="fw-medium">{{ $loanPlan->requires_collateral ? 'Yes' : 'No' }}</td></tr>
                            <tr><td class="text-muted">Max Active Loans</td><td class="fw-medium">{{ $loanPlan->maximum_active_loans }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Fees --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2"></i>Fees</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Processing Fee</td><td class="fw-medium">{{ number_format($loanPlan->processing_fee, 2) }} TZS</td></tr>
                            <tr><td class="text-muted">Insurance Fee</td><td class="fw-medium">{{ number_format($loanPlan->insurance_fee, 2) }} TZS</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Audit Info --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Audit Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:45%">Created</td><td class="fw-medium">{{ $loanPlan->created_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Created By</td><td class="fw-medium">{{ $loanPlan->creator->fullname ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Last Updated</td><td class="fw-medium">{{ $loanPlan->updated_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- Collateral Rules --}}
        @if($loanPlan->collateralRules->count() > 0)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-lock me-2"></i>Collateral Rules (Amount-Based)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Amount Range</th>
                                <th>Required</th>
                                <th>Coverage</th>
                                <th>Min Value</th>
                                <th>Assets</th>
                                <th>Allowed Types</th>
                                <th>Required Docs</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loanPlan->collateralRules->sortBy('minimum_amount') as $rule)
                            <tr>
                                <td class="fw-medium">TSh {{ number_format($rule->minimum_amount, 0) }} - {{ number_format($rule->maximum_amount, 0) }}</td>
                                <td><span class="badge bg-{{ $rule->collateral_required ? 'warning' : 'success' }}">{{ $rule->collateral_required ? 'Yes' : 'No' }}</span></td>
                                <td>{{ $rule->coverage_percentage }}%</td>
                                <td>TSh {{ number_format($rule->minimum_collateral_value, 0) }}</td>
                                <td>{{ $rule->minimum_assets }} - {{ $rule->maximum_assets }}</td>
                                <td><small>{{ $rule->allowed_collateral_types ? implode(', ', array_map(fn($t) => ucfirst($t), $rule->allowed_collateral_types)) : 'Any' }}</small></td>
                                <td><small>{{ $rule->required_document_types ? implode(', ', array_map(fn($t) => str_replace('_', ' ', $t), $rule->required_document_types)) : 'None' }}</small></td>
                                <td><span class="badge bg-{{ $rule->status ? 'success' : 'secondary' }}">{{ $rule->status ? 'Active' : 'Inactive' }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
