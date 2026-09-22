@extends('layouts.app')

@section('title', 'Create Loan Plan')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Create Loan Plan',
        'subtitle' => 'Add a new loan plan',
        'breadcrumb' => [
            ['label' => 'Loan Plans', 'url' => route('loan-plans.index')],
            ['label' => 'Create'],
        ],
        'actions' => '<a href="' . route('loan-plans.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('loan-plans.store') }}" data-validate>
    @csrf

    <div class="row">
        <div class="col-12">

            {{-- Plan Details --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Plan Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" value="{{ old('code') }}" required>
                            @error('code') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organization <span class="text-danger">*</span></label>
                            <select name="organization_id" class="form-select" required>
                                <option value="">Select Organization</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('organization_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Loan Purpose <span class="text-danger">*</span></label>
                            <select name="loan_purpose" class="form-select" required>
                                <option value="">Select Purpose</option>
                                <option value="business" {{ old('loan_purpose') == 'business' ? 'selected' : '' }}>Business</option>
                                <option value="agriculture" {{ old('loan_purpose') == 'agriculture' ? 'selected' : '' }}>Agriculture</option>
                                <option value="education" {{ old('loan_purpose') == 'education' ? 'selected' : '' }}>Education</option>
                                <option value="emergency" {{ old('loan_purpose') == 'emergency' ? 'selected' : '' }}>Emergency</option>
                                <option value="personal" {{ old('loan_purpose') == 'personal' ? 'selected' : '' }}>Personal</option>
                                <option value="development" {{ old('loan_purpose', 'development') == 'development' ? 'selected' : '' }}>Development</option>
                                <option value="other" {{ old('loan_purpose') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('loan_purpose') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                            @error('description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Amount Configuration --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-currency-dollar me-2"></i>Amount Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Minimum Amount <span class="text-danger">*</span></label>
                            <input type="number" name="minimum_amount" class="form-control" value="{{ old('minimum_amount', 50000) }}" step="0.01" min="0" required>
                            @error('minimum_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maximum Amount <span class="text-danger">*</span></label>
                            <input type="number" name="maximum_amount" class="form-control" value="{{ old('maximum_amount', 5000000) }}" step="0.01" min="0" required>
                            @error('maximum_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Interest Configuration --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-percent me-2"></i>Interest Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Interest Rate (%) <span class="text-danger">*</span></label>
                            <input type="number" name="interest_rate" class="form-control" value="{{ old('interest_rate', 1.5) }}" step="0.01" min="0" max="100" required>
                            @error('interest_rate') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Interest Method <span class="text-danger">*</span></label>
                            <select name="interest_method" class="form-select" required>
                                <option value="flat" {{ old('interest_method', 'flat') == 'flat' ? 'selected' : '' }}>Flat</option>
                                <option value="reducing_balance" {{ old('interest_method') == 'reducing_balance' ? 'selected' : '' }}>Reducing Balance</option>
                            </select>
                            @error('interest_method') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Term Configuration --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-range me-2"></i>Term Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Minimum Term (months) <span class="text-danger">*</span></label>
                            <input type="number" name="minimum_term" class="form-control" value="{{ old('minimum_term', 3) }}" min="1" required>
                            @error('minimum_term') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Maximum Term (months) <span class="text-danger">*</span></label>
                            <input type="number" name="maximum_term" class="form-control" value="{{ old('maximum_term', 24) }}" min="1" required>
                            @error('maximum_term') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Repayment Frequency <span class="text-danger">*</span></label>
                            <select name="repayment_frequency" class="form-select" required>
                                <option value="weekly" {{ old('repayment_frequency') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="biweekly" {{ old('repayment_frequency') == 'biweekly' ? 'selected' : '' }}>Biweekly</option>
                                <option value="monthly" {{ old('repayment_frequency', 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="quarterly" {{ old('repayment_frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                            </select>
                            @error('repayment_frequency') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Grace Period (days) <span class="text-danger">*</span></label>
                            <input type="number" name="grace_period" class="form-control" value="{{ old('grace_period', 0) }}" min="0" required>
                            @error('grace_period') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Maximum Active Loans <span class="text-danger">*</span></label>
                            <input type="number" name="maximum_active_loans" class="form-control" value="{{ old('maximum_active_loans', 1) }}" min="1" required>
                            @error('maximum_active_loans') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Requirements --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-check me-2"></i>Requirements</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="requires_guarantor" value="1" id="requiresGuarantor" {{ old('requires_guarantor') ? 'checked' : '' }}>
                                <label class="form-check-label" for="requiresGuarantor">Requires Guarantor</label>
                            </div>
                            @error('requires_guarantor') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Guarantors</label>
                            <input type="number" name="minimum_guarantors" class="form-control" value="{{ old('minimum_guarantors', 0) }}" min="0">
                            @error('minimum_guarantors') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="requires_collateral" value="1" id="requiresCollateral" {{ old('requires_collateral') ? 'checked' : '' }}>
                                <label class="form-check-label" for="requiresCollateral">Requires Collateral</label>
                            </div>
                            @error('requires_collateral') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fees --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2"></i>Fees</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Processing Fee <span class="text-danger">*</span></label>
                            <input type="number" name="processing_fee" class="form-control" value="{{ old('processing_fee', 0) }}" step="0.01" min="0" required>
                            @error('processing_fee') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Insurance Fee <span class="text-danger">*</span></label>
                            <input type="number" name="insurance_fee" class="form-control" value="{{ old('insurance_fee', 0) }}" step="0.01" min="0" required>
                            @error('insurance_fee') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="late_payment_allowed" value="1" id="latePaymentAllowed" {{ old('late_payment_allowed', 1) ? 'checked' : '' }}>
                                <label class="form-check-label" for="latePaymentAllowed">Allow Late Payments</label>
                            </div>
                            @error('late_payment_allowed') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-toggle-on me-2"></i>Status</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('loan-plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Create Plan
                </button>
            </div>

        </div>
    </div>
</form>
@endsection
