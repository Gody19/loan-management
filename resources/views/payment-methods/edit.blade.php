@extends('layouts.app')

@section('title', 'Edit Payment Method')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Edit Payment Method',
        'subtitle' => $paymentMethod->name,
        'breadcrumb' => [
            ['label' => 'Payment Methods', 'url' => route('payment-methods.index')],
            ['label' => $paymentMethod->name],
        ],
        'actions' => '<a href="' . route('payment-methods.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('payment-methods.update', $paymentMethod) }}" data-validate>
    @csrf
    @method('PUT')

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-credit-card me-2"></i>Payment Method Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $paymentMethod->name) }}" required>
                            @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" value="{{ old('code', $paymentMethod->code) }}" required>
                            @error('code') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                @foreach(\App\Enums\PaymentMethodType::cases() as $type)
                                    <option value="{{ $type->value }}" {{ old('type', $paymentMethod->type->value) == $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('type') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organization <span class="text-danger">*</span></label>
                            <select name="organization_id" class="form-select" required>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ old('organization_id', $paymentMethod->organization_id) == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('organization_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ old('status', $paymentMethod->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $paymentMethod->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('payment-methods.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Update Payment Method
                </button>
            </div>

        </div>
    </div>
</form>
@endsection
