<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'FinancePro')) - FinancePro</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/vendor.css', 'resources/css/app.css', 'resources/css/app-custom.css', 'resources/js/app.js'])
    @endif

    {{-- SweetAlert2 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100vh;
        }

        .auth-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Left Panel - Branding */
        .auth-brand-panel {
            flex: 1;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 40%, #084298 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .auth-brand-panel::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .auth-brand-panel::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }

        .auth-brand-content {
            position: relative;
            z-index: 1;
            max-width: 480px;
        }

        .auth-brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .auth-brand-logo-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .auth-brand-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .auth-brand-subtitle {
            font-size: 0.85rem;
            opacity: 0.8;
            margin: 0;
        }

        .auth-brand-headline {
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .auth-brand-desc {
            font-size: 1.05rem;
            opacity: 0.85;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .auth-feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .auth-feature-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            font-size: 0.95rem;
        }

        .auth-feature-list li i {
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* Right Panel - Form */
        .auth-form-panel {
            width: 520px;
            min-width: 520px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem;
            background: #fff;
            position: relative;
            overflow-y: auto;
        }

        .auth-form-wrapper {
            width: 100%;
            max-width: 380px;
        }

        .auth-form-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-form-logo a {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .auth-form-logo-icon {
            width: 40px;
            height: 40px;
            background: #0d6efd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
        }

        .auth-form-logo-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a1d21;
        }

        .auth-form-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1a1d21;
            margin-bottom: 0.25rem;
        }

        .auth-form-subtitle {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1.75rem;
        }

        .auth-form .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.35rem;
        }

        .auth-form .form-control {
            border-radius: 0.5rem;
            padding: 0.6rem 0.85rem;
            font-size: 0.9rem;
            border: 1.5px solid #dee2e6;
            transition: all 0.2s ease;
        }

        .auth-form .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .auth-form .form-control::placeholder {
            color: #adb5bd;
        }

        .auth-form .input-group .btn {
            border: 1.5px solid #dee2e6;
            border-left: none;
            border-radius: 0 0.5rem 0.5rem 0;
            color: #6c757d;
            background: #f8f9fa;
            transition: all 0.2s ease;
        }

        .auth-form .input-group .btn:hover {
            background: #e9ecef;
            color: #0d6efd;
        }

        .auth-form .input-group .form-control {
            border-radius: 0.5rem 0 0 0.5rem;
        }

        .auth-submit-btn {
            width: 100%;
            padding: 0.65rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 0.5rem;
            background: #0d6efd;
            border: none;
            color: #fff;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .auth-submit-btn:hover {
            background: #0b5ed7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }

        .auth-submit-btn:active {
            transform: translateY(0);
        }

        .auth-submit-btn .spinner-border {
            display: none;
        }

        .auth-submit-btn.loading .spinner-border {
            display: inline-block;
        }

        .auth-submit-btn.loading .btn-text {
            display: none;
        }

        .auth-footer-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .auth-footer-links a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .auth-footer-links a:hover {
            color: #0a58ca;
            text-decoration: underline;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e9ecef;
        }

        .auth-divider span {
            font-size: 0.8rem;
            color: #adb5bd;
            white-space: nowrap;
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .auth-brand-panel {
                display: none;
            }

            .auth-form-panel {
                width: 100%;
                min-width: auto;
            }
        }

        @media (max-width: 575.98px) {
            .auth-form-panel {
                padding: 2rem 1.25rem;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-form-wrapper {
            animation: fadeInUp 0.5s ease;
        }

        .auth-brand-content {
            animation: fadeInUp 0.6s ease;
        }

        .auth-feature-list li {
            animation: fadeInUp 0.5s ease;
            animation-fill-mode: both;
        }

        .auth-feature-list li:nth-child(1) { animation-delay: 0.1s; }
        .auth-feature-list li:nth-child(2) { animation-delay: 0.2s; }
        .auth-feature-list li:nth-child(3) { animation-delay: 0.3s; }
        .auth-feature-list li:nth-child(4) { animation-delay: 0.4s; }
        .auth-feature-list li:nth-child(5) { animation-delay: 0.5s; }
    </style>

    @stack('styles')
</head>
<body class="bg-white">

    <div class="auth-wrapper">
        {{-- Left Panel: Branding --}}
        <div class="auth-brand-panel d-none d-lg-flex">
            <div class="auth-brand-content">
                
                <div class="auth-brand-logo">
                    <div class="auth-brand-logo-icon">
                        <i class="bi bi-grid-1x2-fill"></i>
                    </div>
                    <div>
                        <p class="auth-brand-title">FinancePro</p>
                        <p class="auth-brand-subtitle">VICOBA Management System</p>
                    </div>
                </div>

                <h1 class="auth-brand-headline">
                    Manage Your VICOBA.<br>Simplify Your Financial Operations.
                </h1>

                <p class="auth-brand-desc">
                    A secure and modern platform to manage VICOBA groups, members, savings, shares, welfare funds, loans and accounting from one centralized system.
                </p>

                <ul class="auth-feature-list">
                    <li>
                        <i class="bi bi-people"></i>
                        <span>Member Management &amp; Group Organization</span>
                    </li>
                    <li>
                        <i class="bi bi-wallet2"></i>
                        <span>Savings, Shares &amp; Welfare Tracking</span>
                    </li>
                    <li>
                        <i class="bi bi-cash-coin"></i>
                        <span>Loan Management &amp; Repayment Schedules</span>
                    </li>
                    <li>
                        <i class="bi bi-journal-text"></i>
                        <span>Double-Entry Accounting &amp; Reports</span>
                    </li>
                    <li>
                        <i class="bi bi-shield-lock"></i>
                        <span>Role-Based Access &amp; Audit Trails</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Right Panel: Form --}}
        <div class="auth-form-panel">
            <div class="auth-form-wrapper">
                {{-- Mobile Logo --}}
                <div class="auth-form-logo d-lg-none">
                    <a href="/">
                        <div class="auth-form-logo-icon">
                            <i class="bi bi-grid-1x2-fill"></i>
                        </div>
                        <span class="auth-form-logo-text">FinancePro</span>
                    </a>
                </div>

                {{-- Flash Messages --}}
                @include('layouts.components.alerts')

                {{-- Content --}}
                @yield('content')

                {{-- Footer --}}
                <div class="text-center mt-4">
                    <p class="text-muted" style="font-size: 0.8rem;">
                        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Flash Messages via SweetAlert2 --}}
    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: {!! json_encode(session('success')) !!},
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        </script>
    @endif

    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: {!! json_encode(session('error')) !!},
                    timer: 6000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        </script>
    @endif

</body>
</html>
