<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta-description', 'Manage VICOBA groups, members, savings, shares, welfare, loans, repayments and financial operations from one centralized platform.')">

    <title>@yield('title', 'VICOBA & Microfinance Management Platform')</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- SweetAlert2 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    {{-- Vite Assets --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/vendor.css', 'resources/css/app.css', 'resources/css/app-custom.css', 'resources/js/app.js'])
    @endif

    {{-- Landing page custom styles --}}
    <style>
        .landing-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            transition: box-shadow 0.3s ease;
        }
        .landing-nav.scrolled {
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
        }
        .hero-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 50%, #084298 100%);
            color: #fff;
            padding: 6rem 0 5rem;
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }
        .hero-section .container { position: relative; z-index: 1; }
        .feature-card {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 0.75rem;
            padding: 2rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
        }
        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .step-number {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #0d6efd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .cta-section {
            background: linear-gradient(135deg, #0d6efd 0%, #084298 100%);
            color: #fff;
        }
        .section-heading {
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .landing-footer {
            background: #1a1d21;
            color: rgba(255, 255, 255, 0.7);
        }
        .landing-footer a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.2s;
        }
        .landing-footer a:hover {
            color: #fff;
        }
        .btn-landing-primary {
            background: #fff;
            color: #0d6efd;
            border: none;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-landing-primary:hover {
            background: #f0f0f0;
            color: #0a58ca;
            transform: translateY(-1px);
        }
        .btn-landing-outline {
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: #fff;
            background: transparent;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-landing-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.7);
            color: #fff;
        }
        .btn-cta-primary {
            background: #0d6efd;
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-cta-primary:hover {
            background: #0a58ca;
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-cta-outline {
            border: 2px solid #0d6efd;
            color: #0d6efd;
            background: transparent;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-cta-outline:hover {
            background: #0d6efd;
            color: #fff;
        }
        .trust-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .hero-section { padding: 4rem 0 3rem; }
            .hero-section h1 { font-size: 2rem; }
        }
        .accordion-button:not(.collapsed) {
            background-color: rgba(13, 110, 253, 0.08);
            color: #0d6efd;
        }
        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(13, 110, 253, 0.25);
        }
        .accordion-button::after {
            filter: none;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-white">

    {{-- Content --}}
    @yield('content')

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: {!! json_encode(session('success')) !!},
                    timer: 5000,
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Global form loading spinner
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    var submitBtn = form.querySelector('[type="submit"]');
                    if (submitBtn && !submitBtn.classList.contains('no-spinner')) {
                        if (!submitBtn.querySelector('.spinner-border')) {
                            var spinner = document.createElement('span');
                            spinner.className = 'spinner-border spinner-border-sm me-1';
                            spinner.setAttribute('role', 'status');
                            submitBtn.appendChild(spinner);
                        }
                        submitBtn.classList.add('btn-loading');
                        submitBtn.disabled = true;
                    }
                });
            });
        });
    </script>

    @include('partials.ai-chat-widget')

    @stack('scripts')
</body>
</html>
