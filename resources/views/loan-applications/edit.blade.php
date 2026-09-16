@extends('layouts.app')

@section('title', 'Edit Loan Application')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Edit Application ' . $application->application_number,
        'subtitle' => 'Update loan application details',
        'breadcrumb' => [
            ['label' => 'Loan Applications', 'url' => route('loan-applications.index')],
            ['label' => $application->application_number, 'url' => route('loan-applications.show', $application)],
            ['label' => 'Edit'],
        ],
        'actions' => '<a href="' . route('loan-applications.show', $application) . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('loan-applications.update', $application) }}" data-validate>
    @csrf
    @method('PUT')

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Applicant Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Member <span class="text-danger">*</span></label>
                            <select name="member_id" class="form-select" required>
                                <option value="">Select Member</option>
                                @foreach($members as $m)
                                    <option value="{{ $m->id }}" {{ old('member_id', $application->member_id) == $m->id ? 'selected' : '' }}>{{ $m->full_name }} ({{ $m->member_number }})</option>
                                @endforeach
                            </select>
                            @error('member_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select Branch</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ old('branch_id', $application->branch_id) == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">VICOBA Group</label>
                            <select name="vicoba_group_id" class="form-select">
                                <option value="">Select Group (optional)</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}" {{ old('vicoba_group_id', $application->vicoba_group_id) == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                                @endforeach
                            </select>
                            @error('vicoba_group_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2"></i>Loan Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Loan Plan <span class="text-danger">*</span></label>
                            <select name="loan_plan_id" class="form-select" required>
                                <option value="">Select Loan Plan</option>
                                @foreach($loanPlans as $plan)
                                    <option value="{{ $plan->id }}" {{ old('loan_plan_id', $application->loan_plan_id) == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                            @error('loan_plan_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Requested Amount (TZS) <span class="text-danger">*</span></label>
                            <input type="number" name="requested_amount" class="form-control" value="{{ old('requested_amount', $application->requested_amount) }}" step="0.01" min="1" required>
                            @error('requested_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Term (months) <span class="text-danger">*</span></label>
                            <input type="number" name="requested_term" class="form-control" value="{{ old('requested_term', $application->requested_term) }}" min="1" required>
                            @error('requested_term') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Repayment Frequency <span class="text-danger">*</span></label>
                            <select name="repayment_frequency" class="form-select" required>
                                <option value="monthly" {{ old('repayment_frequency', $application->repayment_frequency->value) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="weekly" {{ old('repayment_frequency', $application->repayment_frequency->value) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="biweekly" {{ old('repayment_frequency', $application->repayment_frequency->value) == 'biweekly' ? 'selected' : '' }}>Biweekly</option>
                                <option value="quarterly" {{ old('repayment_frequency', $application->repayment_frequency->value) == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                            </select>
                            @error('repayment_frequency') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Loan Purpose <span class="text-danger">*</span></label>
                            <select name="loan_purpose" class="form-select" required>
                                <option value="">Select Purpose</option>
                                <option value="business" {{ old('loan_purpose', $application->loan_purpose->value) == 'business' ? 'selected' : '' }}>Business</option>
                                <option value="agriculture" {{ old('loan_purpose', $application->loan_purpose->value) == 'agriculture' ? 'selected' : '' }}>Agriculture</option>
                                <option value="education" {{ old('loan_purpose', $application->loan_purpose->value) == 'education' ? 'selected' : '' }}>Education</option>
                                <option value="emergency" {{ old('loan_purpose', $application->loan_purpose->value) == 'emergency' ? 'selected' : '' }}>Emergency</option>
                                <option value="personal" {{ old('loan_purpose', $application->loan_purpose->value) == 'personal' ? 'selected' : '' }}>Personal</option>
                                <option value="development" {{ old('loan_purpose', $application->loan_purpose->value) == 'development' ? 'selected' : '' }}>Development</option>
                                <option value="other" {{ old('loan_purpose', $application->loan_purpose->value) == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('loan_purpose') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Purpose Description</label>
                            <textarea name="purpose_description" class="form-control" rows="3">{{ old('purpose_description', $application->purpose_description) }}</textarea>
                            @error('purpose_description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('loan-applications.show', $application) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Update Application
                </button>
            </div>

        </div>
    </div>
</form>
@endsection
