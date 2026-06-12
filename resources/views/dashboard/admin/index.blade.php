@extends('layouts.app')

@section('content')
<div class="space-y-lg">
    <!-- Header -->
    <div class="mb-2xl">
        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">Admin Dashboard</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">System overview and management</p>
    </div>

    <!-- KPI Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-lg">
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Users</p>
            <p class="font-headline-lg text-headline-lg text-primary">{{ $totalUsers }}</p>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Loans</p>
            <p class="font-headline-lg text-headline-lg text-tertiary">{{ $totalLoans }}</p>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Pending</p>
            <p class="font-headline-lg text-headline-lg text-error">{{ $pendingLoans }}</p>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Approved</p>
            <p class="font-headline-lg text-headline-lg text-tertiary-fixed">{{ $approvedLoans }}</p>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Value</p>
            <p class="font-headline-lg text-headline-lg text-secondary">₱{{ number_format($totalLoanValue, 0) }}</p>
        </div>
    </div>

    <!-- Admin Sections based on Role -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
        @if(auth()->user()->role === 'loan_officer' || auth()->user()->role === 'manager' || auth()->user()->role === 'admin')
        <!-- Pending Loans for Approval -->
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Pending Approvals</h3>
                <a href="#" class="text-primary hover:underline">View All</a>
            </div>
            <div class="space-y-md">
                <p class="text-on-surface-variant text-center py-lg">Pending loans data coming soon</p>
            </div>
        </div>
        @endif

        @if(auth()->user()->role === 'manager' || auth()->user()->role === 'admin')
        <!-- System Reports -->
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">System Reports</h3>
                <a href="#" class="text-primary hover:underline">View All</a>
            </div>
            <div class="space-y-md">
                <p class="text-on-surface-variant text-center py-lg">Reports coming soon</p>
            </div>
        </div>
        @endif

        @if(auth()->user()->role === 'admin')
        <!-- User Management -->
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">User Management</h3>
                <a href="#" class="text-primary hover:underline">Manage Users</a>
            </div>
            <div class="space-y-md">
                <p class="text-on-surface-variant text-center py-lg">User management tools</p>
            </div>
        </div>

        <!-- System Settings -->
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Settings</h3>
                <a href="#" class="text-primary hover:underline">Configure</a>
            </div>
            <div class="space-y-md">
                <p class="text-on-surface-variant text-center py-lg">System configuration options</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
