@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
<h5 class="fw-semibold mb-2">Reset Password</h5>
<p class="text-muted mb-4" style="font-size: 0.85rem;">
    Enter your email address and we'll send you a link to reset your password.
</p>

<form method="POST" action="{{ route('password.email') }}" data-validate>
    @csrf

    {{-- Email --}}
    <div class="mb-4">
        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
        <input type="email" class="form-control @error('email') is-invalid @enderror"
               id="email" name="email" value="{{ old('email') }}"
               placeholder="Enter your email" autofocus>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Submit --}}
    <button type="submit" class="btn btn-primary w-100 vicoba-btn">
        <i class="bi bi-envelope me-1"></i> Send Reset Link
    </button>
</form>

<div class="text-center mt-3">
    <a href="{{ route('login') }}" class="text-decoration-none" style="font-size: 0.85rem;">
        <i class="bi bi-arrow-left me-1"></i> Back to Login
    </a>
</div>
@endsection
