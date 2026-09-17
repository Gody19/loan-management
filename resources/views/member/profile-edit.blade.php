@extends('layouts.member')

@section('title', 'Edit Profile - FinancePro VICOBA')
@section('page-title', 'Edit Profile')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('member.profile') }}">My Profile</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card vicoba-card">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pencil me-2"></i>Edit Profile</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('member.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-semibold text-muted border-bottom pb-2">Contact Information</h6>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                   id="phone" name="phone"
                                   value="{{ old('phone', $member->phone) }}" required>
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="alternate_phone" class="form-label">Alternate Phone</label>
                            <input type="text" class="form-control @error('alternate_phone') is-invalid @enderror"
                                   id="alternate_phone" name="alternate_phone"
                                   value="{{ old('alternate_phone', $member->alternate_phone) }}">
                            @error('alternate_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email"
                                   value="{{ old('email', $member->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="marital_status" class="form-label">Marital Status</label>
                            <select class="form-select @error('marital_status') is-invalid @enderror"
                                    id="marital_status" name="marital_status">
                                <option value="">Select...</option>
                                @foreach(['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed', 'separated' => 'Separated', 'other' => 'Other'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('marital_status', $member->marital_status?->value) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('marital_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <h6 class="fw-semibold text-muted border-bottom pb-2 mt-2">Employment Information</h6>
                        </div>

                        <div class="col-md-6">
                            <label for="occupation" class="form-label">Occupation</label>
                            <input type="text" class="form-control @error('occupation') is-invalid @enderror"
                                   id="occupation" name="occupation"
                                   value="{{ old('occupation', $member->occupation) }}">
                            @error('occupation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="employer_or_business" class="form-label">Employer / Business</label>
                            <input type="text" class="form-control @error('employer_or_business') is-invalid @enderror"
                                   id="employer_or_business" name="employer_or_business"
                                   value="{{ old('employer_or_business', $member->employer_or_business) }}">
                            @error('employer_or_business')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <h6 class="fw-semibold text-muted border-bottom pb-2 mt-2">Address</h6>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control @error('address') is-invalid @enderror"
                                      id="address" name="address" rows="2">{{ old('address', $member->address) }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="region" class="form-label">Region</label>
                            <input type="text" class="form-control @error('region') is-invalid @enderror"
                                   id="region" name="region"
                                   value="{{ old('region', $member->region) }}">
                            @error('region')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="district" class="form-label">District</label>
                            <input type="text" class="form-control @error('district') is-invalid @enderror"
                                   id="district" name="district"
                                   value="{{ old('district', $member->district) }}">
                            @error('district')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="ward" class="form-label">Ward</label>
                            <input type="text" class="form-control @error('ward') is-invalid @enderror"
                                   id="ward" name="ward"
                                   value="{{ old('ward', $member->ward) }}">
                            @error('ward')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="street" class="form-label">Street</label>
                            <input type="text" class="form-control @error('street') is-invalid @enderror"
                                   id="street" name="street"
                                   value="{{ old('street', $member->street) }}">
                            @error('street')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('member.profile') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
