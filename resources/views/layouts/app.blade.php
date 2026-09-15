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
        @vite(['resources/css/app.css', 'resources/css/app-custom.css', 'resources/js/app.js'])
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

    @stack('scripts')

</body>
</html>
