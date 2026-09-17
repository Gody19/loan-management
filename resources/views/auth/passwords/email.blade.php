@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
<div class="auth-form">

    <h2 class="auth-form-title">Forgot Password?</h2>
    <p class="auth-form-subtitle">Enter your email and we'll send you a reset link</p>

    <form method="POST" action="{{ route('password.email') }}" data-validate id="forgotForm">
        @csrf

        {{-- Email --}}
        <div class="mb-4">
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

        {{-- Submit --}}
        <button type="submit" class="auth-submit-btn" id="submitBtn">
            <span class="btn-text">
                <i class="bi bi-envelope me-1"></i> Send Reset Link
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
    document.getElementById('forgotForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
</script>
@endpush
