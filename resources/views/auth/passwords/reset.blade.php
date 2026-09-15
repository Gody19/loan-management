@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
<h5 class="fw-semibold mb-2">Reset Password</h5>
<p class="text-muted mb-4" style="font-size: 0.85rem;">
    Enter your new password below.
</p>

<form method="POST" action="{{ route('password.update') }}" data-validate>
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    {{-- Email --}}
    <div class="mb-3">
        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
        <input type="email" class="form-control @error('email') is-invalid @enderror"
               id="email" name="email" value="{{ $email ?? old('email') }}"
               autofocus>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Password --}}
    <div class="mb-3">
        <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control @error('password') is-invalid @enderror"
               id="password" name="password">
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Confirm Password --}}
    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control"
               id="password_confirmation" name="password_confirmation">
    </div>

    {{-- Submit --}}
    <button type="submit" class="btn btn-primary w-100 vicoba-btn">
        <i class="bi bi-key me-1"></i> Reset Password
    </button>
</form>

<div class="text-center mt-3">
    <a href="{{ route('login') }}" class="text-decoration-none" style="font-size: 0.85rem;">
        <i class="bi bi-arrow-left me-1"></i> Back to Login
    </a>
</div>
@endsection
