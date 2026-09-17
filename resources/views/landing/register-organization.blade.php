@extends('layouts.landing')

@section('title', 'Register Your Organization - FinancePro VICOBA')

@section('content')
<x-landing.navbar />

<section class="py-5" style="padding-top: 6rem !important;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="text-center mb-4">
                    <h1 class="fw-bold mb-2">Register Your Organization</h1>
                    <p class="text-muted">Create your organization and administrator account to get started.</p>
                </div>

                <div class="bg-light rounded-4 p-4 p-md-5">
                    <form method="POST" action="{{ route('register-organization.store') }}">
                        @csrf

                        {{-- Organization Details --}}
                        <h5 class="fw-bold mb-3"><i class="bi bi-building me-2 text-primary"></i>Organization Details</h5>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label for="name" class="form-label fw-medium">Organization Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name"
                                       value="{{ old('name') }}" required autofocus>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="registration_number" class="form-label fw-medium">Registration Number</label>
                                <input type="text" class="form-control @error('registration_number') is-invalid @enderror"
                                       id="registration_number" name="registration_number"
                                       value="{{ old('registration_number') }}">
                                @error('registration_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-medium">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                       id="phone" name="phone"
                                       value="{{ old('phone') }}" required>
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                       id="email" name="email"
                                       value="{{ old('email') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="region" class="form-label fw-medium">Region</label>
                                <input type="text" class="form-control @error('region') is-invalid @enderror"
                                       id="region" name="region"
                                       value="{{ old('region') }}">
                                @error('region')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="district" class="form-label fw-medium">District</label>
                                <input type="text" class="form-control @error('district') is-invalid @enderror"
                                       id="district" name="district"
                                       value="{{ old('district') }}">
                                @error('district')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label fw-medium">Address</label>
                                <textarea class="form-control @error('address') is-invalid @enderror"
                                          id="address" name="address" rows="2">{{ old('address') }}</textarea>
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Administrator Details --}}
                        <h5 class="fw-bold mb-3"><i class="bi bi-person-gear me-2 text-primary"></i>Administrator Account</h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="admin_name" class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('admin_name') is-invalid @enderror"
                                       id="admin_name" name="admin_name"
                                       value="{{ old('admin_name') }}" required>
                                @error('admin_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_email" class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('admin_email') is-invalid @enderror"
                                       id="admin_email" name="admin_email"
                                       value="{{ old('admin_email') }}" required>
                                @error('admin_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_phone" class="form-label fw-medium">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('admin_phone') is-invalid @enderror"
                                       id="admin_phone" name="admin_phone"
                                       value="{{ old('admin_phone') }}" required>
                                @error('admin_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_password" class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control @error('admin_password') is-invalid @enderror"
                                       id="admin_password" name="admin_password" required>
                                @error('admin_password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_password_confirmation" class="form-label fw-medium">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control"
                                       id="admin_password_confirmation" name="admin_password_confirmation" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back
                            </a>
                            <button type="submit" class="btn btn-cta-primary btn-lg">
                                <i class="bi bi-check-lg me-1"></i> Register Organization
                            </button>
                        </div>
                    </form>
                </div>

                <p class="text-center text-muted mt-3" style="font-size: 0.85rem;">
                    Already have an account? <a href="{{ route('login') }}" class="text-primary fw-medium">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</section>

<x-landing.footer />

@endsection
