<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FinancePro | Customer Dashboard')</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
        }
    </style>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "tertiary-fixed-dim": "#4edea3",
                        "on-secondary-container": "#586377",
                        "surface-container-low": "#eff4ff",
                        "surface-container-highest": "#d3e4fe",
                        "secondary-fixed-dim": "#bcc7de",
                        "on-primary-fixed-variant": "#003ea8",
                        "on-background": "#0b1c30",
                        "primary-fixed-dim": "#b4c5ff",
                        "on-primary-fixed": "#00174b",
                        "surface-dim": "#cbdbf5",
                        "surface-tint": "#0053db",
                        "on-primary": "#ffffff",
                        "tertiary": "#006242",
                        "on-tertiary-container": "#bdffdb",
                        "on-surface-variant": "#434655",
                        "on-tertiary-fixed-variant": "#005236",
                        "primary-fixed": "#dbe1ff",
                        "inverse-surface": "#213145",
                        "outline": "#737686",
                        "surface": "#f8f9ff",
                        "inverse-primary": "#b4c5ff",
                        "primary": "#004ac6",
                        "on-error": "#ffffff",
                        "on-secondary-fixed-variant": "#3c475a",
                        "surface-bright": "#f8f9ff",
                        "on-secondary-fixed": "#111c2d",
                        "background": "#f8f9ff",
                        "primary-container": "#2563eb",
                        "error": "#ba1a1a",
                        "secondary": "#545f73",
                        "outline-variant": "#c3c6d7",
                        "on-error-container": "#93000a",
                        "tertiary-fixed": "#6ffbbe",
                        "tertiary-container": "#007d55",
                        "surface-container-high": "#dce9ff",
                        "on-surface": "#0b1c30",
                        "surface-container": "#e5eeff",
                        "on-tertiary": "#ffffff",
                        "secondary-fixed": "#d8e3fb",
                        "secondary-container": "#d5e0f8",
                        "on-tertiary-fixed": "#002113",
                        "on-secondary": "#ffffff",
                        "surface-variant": "#d3e4fe",
                        "error-container": "#ffdad6",
                        "inverse-on-surface": "#eaf1ff",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-container": "#eeefff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "3xl": "64px",
                        "margin-mobile": "16px",
                        "gutter": "24px",
                        "md": "16px",
                        "lg": "24px",
                        "base": "4px",
                        "2xl": "48px",
                        "container-max": "1280px",
                        "sm": "8px",
                        "xs": "4px",
                        "xl": "32px"
                    },
                    "fontFamily": {
                        "label-lg": ["Inter"],
                        "headline-lg": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "body-lg": ["Inter"],
                        "code": ["Inter"],
                        "display-lg": ["Inter"],
                        "headline-sm": ["Inter"],
                        "display-md": ["Inter"],
                        "body-md": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-md": ["Inter"],
                        "body-sm": ["Inter"]
                    },
                    "fontSize": {
                        "label-lg": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "600"}],
                        "headline-lg": ["30px", {"lineHeight": "38px", "fontWeight": "600"}],
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "headline-sm": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                        "display-md": ["36px", {"lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "label-md": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
                        "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                        "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}]
                    }
                },
            },
        }
    </script>
    
    @stack('styles')
</head>
<body class="bg-background text-on-background font-body-md min-h-screen">
    <!-- Sidebar -->
    @include('components.sidebar')
    
    <!-- Main Content Area -->
    <main class="md:ml-64 min-h-screen">
        <!-- Navbar -->
        @include('components.navbar')
        
        <!-- Page Content -->
        <div class="p-margin-mobile md:p-xl space-y-xl max-w-container-max mx-auto">
            @yield('content')
        </div>
        
        <!-- Footer -->
        @include('components.footer')
    </main>
    
    <!-- Visual Polish: Floating Animation -->
    <div class="fixed inset-0 pointer-events-none z-[-1] overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/5 blur-[120px] rounded-full animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-[500px] h-[500px] bg-tertiary/5 blur-[150px] rounded-full animate-pulse" style="animation-delay: 2s;"></div>
    </div>
    
    @stack('scripts')
</body>
</html>