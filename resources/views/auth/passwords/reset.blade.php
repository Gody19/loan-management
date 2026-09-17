@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
<div class="auth-form">

    <h2 class="auth-form-title">Reset Password</h2>
    <p class="auth-form-subtitle">Enter your new password below</p>

    <form method="POST" action="{{ route('password.update') }}" data-validate id="resetForm">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border-radius: 0.5rem 0 0 0.5rem;">
                    <i class="bi bi-envelope text-muted"></i>
                </span>
                <input type="email" class="form-control border-start-0 @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ $email ?? old('email') }}"
                       placeholder="you@example.com" autofocus>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border-radius: 0.5rem 0 0 0.5rem;">
                    <i class="bi bi-lock text-muted"></i>
                </span>
                <input type="password" class="form-control border-start-0 border-end-0 @error('password') is-invalid @enderror"
                       id="password" name="password"
                       placeholder="Enter new password">
                <button class="btn bg-light" type="button" onclick="togglePassword()"
                        style="border-radius: 0 0.5rem 0.5rem 0; border-color: #dee2e6;">
                    <i class="bi bi-eye text-muted" id="toggleIcon"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Confirm Password --}}
        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border-radius: 0.5rem 0 0 0.5rem;">
                    <i class="bi bi-lock text-muted"></i>
                </span>
                <input type="password" class="form-control border-start-0"
                       id="password_confirmation" name="password_confirmation"
                       placeholder="Confirm new password">
            </div>
        </div>

        {{-- Submit --}}
        <button type="submit" class="auth-submit-btn" id="submitBtn">
            <span class="btn-text">
                <i class="bi bi-key me-1"></i> Reset Password
            </span>
            <span class="spinner-border spinner-border-sm" role="status"></span>
        </button>
    </form>

    <div class="text-center mt-3">
        <a href="{{ route('login') }}" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none; font-weight: 500;">
            <i class="bi bi-arrow-left me-1"></i> Back to Login
        </a>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    }

    document.getElementById('resetForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
</script>
@endpush
