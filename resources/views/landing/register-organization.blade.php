@extends('layouts.landing')

@section('title', 'Register Your Organization - FinancePro VICOBA')

@section('content')
<x-landing.navbar />

<section class="py-5" style="padding-top: 6rem !important;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-9">
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

                        <hr class="mb-4">

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

                        <div class="bg-white rounded-3 p-3 mb-4" style="border-left: 3px solid #0d6efd;">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1 text-primary"></i>
                                The administrator account will have full access to manage your organization, members and financial operations.
                            </small>
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

{{-- Benefits of Registering --}}
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading display-6 fw-bold mb-3">Why Register with FinancePro?</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Everything you need to digitize your VICOBA operations, from member management to financial reporting.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto"><i class="bi bi-speedometer2"></i></div>
                    <h5 class="fw-bold mb-2">Quick Setup</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Get started in minutes. Register your organization and begin managing members immediately.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto"><i class="bi bi-people"></i></div>
                    <h5 class="fw-bold mb-2">Member Management</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Register unlimited members, track membership status and manage group assignments.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-warning bg-opacity-10 text-warning mx-auto"><i class="bi bi-wallet2"></i></div>
                    <h5 class="fw-bold mb-2">Financial Tracking</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Track savings, shares, welfare contributions and loan repayments with full audit trails.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-danger bg-opacity-10 text-danger mx-auto"><i class="bi bi-bar-chart-line"></i></div>
                    <h5 class="fw-bold mb-2">Reports &amp; Analytics</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Access financial reports, dashboards and insights to make informed decisions.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<x-landing.footer />

@endsection
