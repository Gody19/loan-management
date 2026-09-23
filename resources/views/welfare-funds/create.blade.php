@extends('layouts.app')

@section('title', 'Create Welfare Fund')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Create Welfare Fund',
        'subtitle' => 'Add a new welfare fund',
        'breadcrumb' => [
            ['label' => 'Welfare Funds', 'url' => route('welfare-funds.index')],
            ['label' => 'Create'],
        ],
        'actions' => '<a href="' . route('welfare-funds.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('welfare-funds.store') }}" data-validate>
    @csrf

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-heart me-2"></i>Fund Details</h6>
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
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach(\App\Enums\SavingsAccountStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ old('status', 'active') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('status') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                            @error('description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-currency-dollar me-2"></i>Contribution Settings</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Contribution Type <span class="text-danger">*</span></label>
                            <select name="contribution_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="fixed" {{ old('contribution_type') == 'fixed' ? 'selected' : '' }}>Fixed</option>
                                <option value="flexible" {{ old('contribution_type') == 'flexible' ? 'selected' : '' }}>Flexible</option>
                                <option value="percentage" {{ old('contribution_type') == 'percentage' ? 'selected' : '' }}>Percentage</option>
                            </select>
                            @error('contribution_type') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="default_amount" class="form-control" value="{{ old('default_amount') }}" required>
                            @error('default_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('welfare-funds.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Create Fund
                </button>
            </div>

</form>
@endsection
