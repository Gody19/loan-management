@extends('layouts.member')

@section('title', 'Settings - FinancePro VICOBA')
@section('page-title', 'Settings')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Settings</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">

        {{-- Change Password --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-2"></i>Change Password</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('member.settings.password') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-medium">Current Password</label>
                        <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                               id="current_password" name="current_password" required autocomplete="current-password">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-medium">New Password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               id="password" name="password" required autocomplete="new-password" minlength="8">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label fw-medium">Confirm New Password</label>
                        <input type="password" class="form-control"
                               id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Account Info --}}
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Account Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div class="fw-medium">{{ $user->email }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Username</div>
                        <div class="fw-medium">{{ $user->username }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Member Number</div>
                        <div class="fw-medium">{{ $member->member_number ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div>
                            <span class="badge bg-{{ $user->status->value === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($user->status->value) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Session Info --}}
        <div class="card vicoba-card">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-lock me-2"></i>Security Tips</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0 small text-muted">
                    <li class="mb-2">Use a strong password with at least 8 characters, including letters and numbers.</li>
                    <li class="mb-2">Never share your login credentials with anyone.</li>
                    <li class="mb-2">Change your password regularly for better security.</li>
                    <li class="mb-0">Always log out when using a shared or public device.</li>
                </ul>
            </div>
        </div>

    </div>
</div>
@endsection
