@extends('layouts.auth')

@section('title', 'Login')

@section('content')
<h5 class="fw-semibold mb-3">Sign in to your account</h5>

<form method="POST" action="{{ route('login') }}">
    @csrf

    {{-- Email --}}
    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror"
               id="email" name="email" value="{{ old('email') }}"
               placeholder="Enter your email" required autofocus>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Password --}}
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <input type="password" class="form-control @error('password') is-invalid @enderror"
                   id="password" name="password"
                   placeholder="Enter your password" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                <i class="bi bi-eye" id="toggleIcon"></i>
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
            <label class="form-check-label" for="remember" style="font-size: 0.85rem;">
                Remember me
            </label>
        </div>
        <a href="{{ route('password.request') }}" class="text-decoration-none" style="font-size: 0.85rem;">
            Forgot password?
        </a>
    </div>

    {{-- Submit --}}
    <button type="submit" class="btn btn-primary w-100 vicoba-btn">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
    </button>
</form>

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
</script>
@endpush
