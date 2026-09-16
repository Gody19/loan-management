<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'FinancePro')) - FinancePro</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/vendor.css', 'resources/css/app.css', 'resources/css/app-custom.css', 'resources/js/app.js'])
    @endif

    {{-- SweetAlert2 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="bg-light">

    <div class="min-vh-100 d-flex align-items-center justify-content-center">
        <div class="w-100" style="max-width: 420px;">
            {{-- Logo --}}
            <div class="text-center mb-4">
                <a href="/" class="text-decoration-none">
                    <h2 class="fw-bold text-primary mb-1">{{ config('app.name', 'FinancePro') }}</h2>
                    <p class="text-muted small">VICOBA Management System</p>
                </a>
            </div>

            {{-- Flash Messages --}}
            @include('layouts.components.alerts')

            {{-- Content --}}
            <div class="card vicoba-card shadow-sm">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>

            {{-- Footer --}}
            <div class="text-center mt-4">
                <p class="text-muted small mb-0">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    @stack('scripts')

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
