<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'My Account - FinancePro VICOBA')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/vendor.css', 'resources/css/app.css', 'resources/css/app-custom.css', 'resources/js/app.js'])
    @endif

    @stack('styles')
</head>
<body class="bg-light">

    {{-- Sidebar --}}
    @include('layouts.components.member-sidebar')

    {{-- Main Content --}}
    <div class="vicoba-main" id="mainContent">

        {{-- Top Navbar --}}
        @include('layouts.components.member-navbar')

        {{-- Page Content --}}
        <div class="p-4 flex-grow-1">
            @include('layouts.components.alerts')

            @hasSection('breadcrumb')
                <div class="mb-3">
                    @yield('breadcrumb')
                </div>
            @endif

            @hasSection('page-header')
                @yield('page-header')
            @endif

            @yield('content')
        </div>

        {{-- Footer --}}
        @include('layouts.components.footer')
    </div>

    {{-- Mobile Sidebar Overlay --}}
    <div class="modal-backdrop fade d-none" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Flash Messages --}}
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

    @auth
        @if (auth()->user()->can('ai.use'))
            @include('partials.ai-chat-widget', ['suggestions' => ['What is my current loan balance?', 'Show me my recent savings', 'What is my shares summary?', 'Show my welfare balance']])
        @endif
    @endauth

    @stack('scripts')

</body>
</html>
