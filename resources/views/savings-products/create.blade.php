@extends('layouts.app')

@section('title', 'Create Savings Plan')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Create Savings Plan',
        'subtitle' => 'Add a new savings plan',
        'breadcrumb' => [
            ['label' => 'Savings Plans', 'url' => route('savings-products.index')],
            ['label' => 'Create'],
        ],
        'actions' => '<a href="' . route('savings-products.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('savings-products.store') }}" data-validate>
    @csrf

            {{-- Product Details --}}
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
                        <div class="col-md-4">
                            <label class="form-label">Minimum Amount <span class="text-danger">*</span></label>
                            <input type="number" name="minimum_amount" class="form-control" value="{{ old('minimum_amount') }}" step="0.01" min="0" required>
                            @error('minimum_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Maximum Amount <span class="text-danger">*</span></label>
                            <input type="number" name="maximum_amount" class="form-control" value="{{ old('maximum_amount') }}" step="0.01" min="0" required>
                            @error('maximum_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Balance</label>
                            <input type="number" name="minimum_balance" class="form-control" value="{{ old('minimum_balance') }}" step="0.01" min="0">
                            @error('minimum_balance') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Withdrawal Settings --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-arrow-up-right me-2"></i>Withdrawal Settings</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="allow_withdrawal" value="1" id="allowWithdrawal" {{ old('allow_withdrawal') ? 'checked' : '' }}>
                                <label class="form-check-label" for="allowWithdrawal">Allow Withdrawals</label>
                            </div>
                            @error('allow_withdrawal') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Withdrawal Limit</label>
                            <input type="number" name="withdrawal_limit" class="form-control" value="{{ old('withdrawal_limit') }}" step="0.01" min="0">
                            @error('withdrawal_limit') <span class="text-danger small">{{ $message }}</span> @enderror
                            <div class="form-text">Maximum amount per withdrawal. Leave empty for no limit.</div>
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
                <a href="{{ route('savings-products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Create Plan
                </button>
            </div>

</form>
@endsection
