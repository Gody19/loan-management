@extends('layouts.app')

@section('title', 'Create Approval Level')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Create Approval Level',
        'subtitle' => 'Add a new loan approval level',
        'breadcrumb' => [
            ['label' => 'Approval Levels', 'url' => route('loan-approval-levels.index')],
            ['label' => 'Create'],
        ],
        'actions' => '<a href="' . route('loan-approval-levels.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('loan-approval-levels.store') }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-layers me-2"></i>Approval Level Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
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
                            <label class="form-label">Level <span class="text-danger">*</span></label>
                            <input type="number" name="level" class="form-control" value="{{ old('level', 1) }}" min="1" required>
                            @error('level') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Required Permission</label>
                            <input type="text" name="required_permission" class="form-control" value="{{ old('required_permission') }}" placeholder="e.g. loan_application.approve">
                            @error('required_permission') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Amount (TZS) <span class="text-danger">*</span></label>
                            <input type="number" name="minimum_amount" class="form-control" value="{{ old('minimum_amount', 0) }}" step="0.01" min="0" required>
                            @error('minimum_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maximum Amount (TZS) <span class="text-danger">*</span></label>
                            <input type="number" name="maximum_amount" class="form-control" value="{{ old('maximum_amount') }}" step="0.01" min="0" required>
                            @error('maximum_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', 1) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                            @error('is_active') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('loan-approval-levels.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Create Level
                </button>
            </div>

        </div>
    </div>
</form>
@endsection
