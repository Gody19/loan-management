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

    {{-- Permission Denied Toast --}}
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
        <div id="permissionToast" class="toast align-items-center text-bg-danger border-0" role="alert" data-bs-autohide="false">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-shield-lock me-2"></i>
                    <strong>Access Denied!</strong> You don't have permission to perform this action.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    {{-- Global Permission Denied Handler --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if user lacks permission for current action
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('error') === 'unauthorized') {
                showToastAndRedirect('Access Denied! You don\'t have permission for this action.');
            }

            // Intercept 403 responses from AJAX calls
            document.addEventListener('ajaxerror', function(e) {
                if (e.detail && e.detail.status === 403) {
                    showToastAndRedirect('Access Denied! You don\'t have permission for this action.');
                }
            });

            // Global fetch interceptor for 403
            const originalFetch = window.fetch;
            window.fetch = function() {
                return originalFetch.apply(this, arguments).then(function(response) {
                    if (response.status === 403) {
                        showToastAndRedirect('Access Denied! You don\'t have permission for this action.');
                    }
                    return response;
                });
            };
        });

        function showToastAndRedirect(message) {
            const toast = document.getElementById('permissionToast');
            if (toast) {
                toast.querySelector('.toast-body').innerHTML =
                    '<i class="bi bi-shield-lock me-2"></i><strong>Access Denied!</strong> ' + message;
                const bsToast = new bootstrap.Toast(toast, { autohide: false });
                bsToast.show();

                setTimeout(function() {
                    window.location.href = '{{ route("dashboard") }}';
                }, 3000);
            }
        }
    </script>

    @stack('scripts')

</body>
</html>
