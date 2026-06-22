@extends('layouts.app')

@section('content')
<div class="space-y-lg">
    <div class="mb-2xl">
        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">Admin Dashboard</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">System overview and management</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-md lg:gap-lg">
        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Users</p>
            <p class="font-headline-lg text-headline-lg text-primary">{{ $totalUsers }}</p>
        </div>

        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Loans</p>
            <p class="font-headline-lg text-headline-lg text-tertiary">{{ $totalLoans }}</p>
        </div>

        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Pending</p>
            <p class="font-headline-lg text-headline-lg text-error">{{ $pendingLoans }}</p>
        </div>

        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Approved</p>
            <p class="font-headline-lg text-headline-lg text-tertiary-fixed">{{ $approvedLoans }}</p>
        </div>

        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm col-span-2 md:col-span-3 lg:col-span-1">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Value</p>
            <p class="font-headline-lg text-headline-lg text-secondary">{{ \App\Helpers\Currency::tzs($totalLoanValue) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-md lg:gap-lg">
        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm border-l-4 border-l-tertiary">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Collected</p>
            <p class="font-headline-lg text-headline-lg text-tertiary">{{ \App\Helpers\Currency::tzs($totalCollected) }}</p>
        </div>

        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm border-l-4 border-l-primary">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Collection Rate</p>
            <p class="font-headline-lg text-headline-lg text-primary">{{ $collectionRate }}%</p>
        </div>

        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm border-l-4 border-l-error">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Overdue Installments</p>
            <p class="font-headline-lg text-headline-lg text-error">{{ $overduePayments }}</p>
        </div>

        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm border-l-4 border-l-secondary">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Today's Collections</p>
            <p class="font-headline-lg text-headline-lg text-secondary">{{ \App\Helpers\Currency::tzs($todayPayments) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
        @if(in_array(auth()->user()->role, ['admin', 'manager', 'loan_officer']))
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Pending Approvals</h3>
                <a href="{{ route('loans.pending') }}" class="text-primary hover:underline font-label-md">View All</a>
            </div>
            <div class="space-y-md">
                @php
                    $recentPending = \App\Models\Loan::where('status', 'pending')->with('user')->latest()->take(5)->get();
                @endphp
                @forelse($recentPending as $loan)
                    <div class="flex items-center justify-between py-md border-b border-outline-variant last:border-b-0">
                        <div>
                            <p class="font-body-md text-on-surface">{{ $loan->user->fullname }}</p>
                            <p class="text-body-sm text-on-surface-variant">{{ $loan->purpose }} &middot; {{ \App\Helpers\Currency::tzs($loan->amount) }}</p>
                        </div>
                        <span class="px-sm py-xs bg-surface-container-high text-on-surface-variant font-label-md text-[10px] rounded uppercase tracking-widest font-bold">{{ $loan->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-on-surface-variant text-center py-lg">No pending loan applications.</p>
                @endforelse
            </div>
        </div>
        @endif

        @if(in_array(auth()->user()->role, ['admin', 'manager']))
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Quick Stats</h3>
            </div>
            <div class="grid grid-cols-2 gap-md">
                <div class="p-md bg-surface-container-low rounded-lg text-center">
                    <p class="font-label-md text-on-surface-variant">Approval Rate</p>
                    @php $total = max($totalLoans, 1); $rate = round(($approvedLoans / $total) * 100); @endphp
                    <p class="font-headline-md text-headline-md text-tertiary mt-sm">{{ $rate }}%</p>
                </div>
                <div class="p-md bg-surface-container-low rounded-lg text-center">
                    <p class="font-label-md text-on-surface-variant">Avg Loan</p>
                    @php $avg = $totalLoans > 0 ? $totalLoanValue / $totalLoans : 0; @endphp
                    <p class="font-headline-md text-headline-md text-primary mt-sm">{{ \App\Helpers\Currency::tzs($avg) }}</p>
                </div>
                <div class="p-md bg-surface-container-low rounded-lg text-center">
                    <p class="font-label-md text-on-surface-variant">Pending Payments</p>
                    <p class="font-headline-md text-headline-md text-secondary mt-sm">{{ $pendingPayments }}</p>
                </div>
                <div class="p-md bg-surface-container-low rounded-lg text-center">
                    <p class="font-label-md text-on-surface-variant">Overdue</p>
                    <p class="font-headline-md text-headline-md text-error mt-sm">{{ $overduePayments }}</p>
                </div>
            </div>
        </div>
        @endif

        @if(auth()->user()->role === 'admin')
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Recent Payments</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-outline-variant">
                            <th class="px-md py-sm font-label-md text-on-surface-variant">Borrower</th>
                            <th class="px-md py-sm font-label-md text-on-surface-variant">Installment</th>
                            <th class="px-md py-sm font-label-md text-on-surface-variant text-right">Amount</th>
                            <th class="px-md py-sm font-label-md text-on-surface-variant">Date</th>
                            <th class="px-md py-sm font-label-md text-on-surface-variant">Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @forelse($recentPayments as $payment)
                            <tr>
                                <td class="px-md py-sm font-body-md text-on-surface">{{ $payment->loan->user->fullname ?? 'N/A' }}</td>
                                <td class="px-md py-sm text-on-surface-variant">#{{ $payment->installment_number }}</td>
                                <td class="px-md py-sm text-right font-body-md text-tertiary">{{ \App\Helpers\Currency::tzs($payment->paid_amount) }}</td>
                                <td class="px-md py-sm text-on-surface-variant">{{ $payment->paid_date?->format('M d, Y') ?? '-' }}</td>
                                <td class="px-md py-sm text-body-sm text-on-surface-variant font-mono">{{ $payment->reference_number ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-md py-lg text-center text-on-surface-variant">No payments received yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if(auth()->user()->role === 'admin')
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">User Management</h3>
                <a href="{{ route('admin.users') }}" class="text-primary hover:underline font-label-md">Manage Users</a>
            </div>
            <p class="text-on-surface-variant text-body-md">{{ $totalUsers }} registered users in the system.</p>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between mb-lg">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">System Settings</h3>
            </div>
            <p class="text-on-surface-variant text-body-md">Configure loan interest rates, approval rules, and system preferences.</p>
        </div>
        @endif
    </div>
</div>
@endSection
