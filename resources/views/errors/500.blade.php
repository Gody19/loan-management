<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 - Server Error | {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/css/app-custom.css'])
    @endif
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: linear-gradient(135deg, #1a1f36 0%, #0f1225 50%, #1a237e 100%);">
    <div class="text-center px-4">
        <div class="mb-4">
            <div class="bg-danger bg-opacity-15 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
            </div>
        </div>
        <h1 class="display-1 fw-bold text-white mb-0" style="font-size: 6rem; line-height: 1;">500</h1>
        <h3 class="text-white-50 fw-semibold mb-3">Server Error</h3>
        <p class="text-white-50 mb-4" style="max-width: 420px; margin: 0 auto; font-size: 0.95rem;">
            Something went wrong on our end. Please try again later or contact your administrator.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="{{ route('dashboard') }}" class="btn btn-primary px-4 py-2">
                <i class="bi bi-house me-1"></i> Go to Dashboard
            </a>
            <a href="javascript:location.reload()" class="btn btn-outline-light px-4 py-2">
                <i class="bi bi-arrow-clockwise me-1"></i> Try Again
            </a>
        </div>
    </div>
</body>
</html>
