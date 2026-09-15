<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 - Page Expired</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/css/app-custom.css'])
    @endif
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center">
        <div class="mb-4">
            <i class="bi bi-clock-history text-info" style="font-size: 5rem;"></i>
        </div>
        <h1 class="display-4 fw-bold text-dark">419</h1>
        <h3 class="text-muted mb-3">Page Expired</h3>
        <p class="text-muted mb-4" style="max-width: 400px; margin: 0 auto;">
            This page has expired due to inactivity. Please refresh and try again.
        </p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary vicoba-btn">
            <i class="bi bi-house me-1"></i> Back to Dashboard
        </a>
    </div>
</body>
</html>
