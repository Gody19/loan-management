<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'FinancePro'))</title>

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

    @stack('styles')
</head>
<body class="bg-light">

    {{-- Sidebar --}}
    @include('layouts.components.sidebar')

    {{-- Main Content --}}
    <div class="vicoba-main" id="mainContent">

        {{-- Top Navbar --}}
        @include('layouts.components.navbar')

        {{-- Page Content --}}
        <div class="p-4">
            {{-- Flash Messages --}}
            @include('layouts.components.alerts')

            {{-- Breadcrumb --}}
            @hasSection('breadcrumb')
                <div class="mb-3">
                    @yield('breadcrumb')
                </div>
            @endif

            {{-- Page Header --}}
            @hasSection('page-header')
                @yield('page-header')
            @endif

            {{-- Main Content --}}
            @yield('content')
        </div>

        {{-- Footer --}}
        @include('layouts.components.footer')

    </div>

    {{-- Mobile Sidebar Overlay --}}
    <div class="modal-backdrop fade d-none" id="sidebarOverlay" onclick="toggleSidebar()"></div>

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

    @if(session('warning'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: {!! json_encode(session('warning')) !!},
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        </script>
    @endif

    @if(session('info'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'info',
                    title: 'Info',
                    text: {!! json_encode(session('info')) !!},
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        </script>
    @endif

    {{-- Global Confirm Handler (replaces native confirm()) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Override native confirm with SweetAlert2
            window._originalConfirm = window.confirm;
            window.confirm = function(message) {
                return new Promise(function(resolve) {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, proceed',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then(function(result) {
                        resolve(result.isConfirmed);
                    });
                });
            };

            // Global data-confirm handler for forms
            document.addEventListener('submit', function(e) {
                var form = e.target;
                if (form.dataset.confirm) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: form.dataset.confirm,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, proceed',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                    return false;
                }
            });

            // Global 403 handler
            document.addEventListener('ajaxerror', function(e) {
                if (e.detail && e.detail.status === 403) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Access Denied',
                        text: 'You don\'t have permission to perform this action.',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.href = '{{ route("dashboard") }}';
                    });
                }
            });

            const originalFetch = window.fetch;
            window.fetch = function() {
                return originalFetch.apply(this, arguments).then(function(response) {
                    if (response.status === 403) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Access Denied',
                            text: 'You don\'t have permission to perform this action.',
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(function() {
                            window.location.href = '{{ route("dashboard") }}';
                        });
                    }
                    return response;
                });
            };
        });
    </script>

    @stack('scripts')

</body>
</html>
