@extends('layouts.auth')

@section('title', 'Login')

@section('content')
<div class="auth-form">

    <h2 class="auth-form-title">Welcome back</h2>
    <p class="auth-form-subtitle">Sign in to your account to continue</p>

    <form method="POST" action="{{ route('login') }}" data-validate id="loginForm">
        @csrf

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border-radius: 0.5rem 0 0 0.5rem;">
                    <i class="bi bi-envelope text-muted"></i>
                </span>
                <input type="email" class="form-control border-start-0 @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email') }}"
                       placeholder="you@example.com" autofocus>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border-radius: 0.5rem 0 0 0.5rem;">
                    <i class="bi bi-lock text-muted"></i>
                </span>
                <input type="password" class="form-control border-start-0 border-end-0 @error('password') is-invalid @enderror"
                       id="password" name="password"
                       placeholder="Enter your password">
                <button class="btn bg-light" type="button" onclick="togglePassword()" id="toggleBtn"
                        style="border-radius: 0 0.5rem 0.5rem 0; border-color: #dee2e6;">
                    <i class="bi bi-eye text-muted" id="toggleIcon"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Remember Me & Forgot Password --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember"
                       {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember" style="font-size: 0.85rem; color: #6c757d;">
                    Remember me
                </label>
            </div>
            <a href="{{ route('password.request') }}" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none; font-weight: 500;">
                Forgot password?
            </a>
        </div>

        {{-- Submit --}}
        <button type="submit" class="auth-submit-btn" id="submitBtn">
            <span class="btn-text">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </span>
            <span class="spinner-border spinner-border-sm" role="status"></span>
        </button>
    </form>

    <div class="auth-divider">
        <span>New here?</span>
    </div>

    <div class="auth-footer-links">
        <a href="{{ route('register-organization') }}">
            <i class="bi bi-building me-1"></i> Register your organization
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

    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
</script>
@endpush
