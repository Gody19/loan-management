@extends('layouts.app')

@section('content')
<div class="space-y-lg">
    <!-- Header -->
    <div class="mb-2xl">
        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">Dashboard</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Welcome back, {{ auth()->user()->name }}!</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-lg">
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-label-md text-label-md text-on-surface-variant mb-xs">Pending Loans</p>
                    <p class="font-headline-md text-headline-md text-primary">{{ $loansCount }}</p>
                </div>
                <span class="material-symbols-outlined text-primary text-3xl">assignment</span>
            </div>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-label-md text-label-md text-on-surface-variant mb-xs">Total Loans</p>
                    <p class="font-headline-md text-headline-md text-tertiary">{{ $totalLoans }}</p>
                </div>
                <span class="material-symbols-outlined text-tertiary text-3xl">account_balance</span>
            </div>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-label-md text-label-md text-on-surface-variant mb-xs">Portfolios</p>
                    <p class="font-headline-md text-headline-md text-secondary">{{ $portfolios->count() }}</p>
                </div>
                <span class="material-symbols-outlined text-secondary text-3xl">trending_up</span>
            </div>
        </div>

        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-label-md text-label-md text-on-surface-variant mb-xs">Active Budgets</p>
                    <p class="font-headline-md text-headline-md text-tertiary-fixed">{{ $budgets->count() }}</p>
                </div>
                <span class="material-symbols-outlined text-tertiary-fixed text-3xl">wallet</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
        <!-- Quick Actions -->
        <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <h3 class="font-headline-sm text-headline-sm text-on-surface mb-lg">Quick Actions</h3>
            <div class="space-y-md">
                <a href="{{ route('loans.create') }}" class="block w-full p-md bg-primary-container text-on-primary-container rounded-lg hover:bg-primary-container/90 transition-colors">
                    <span class="material-symbols-outlined inline mr-xs">add</span>
                    Apply for Loan
                </a>
                <a href="{{ route('dashboard.user') }}" class="block w-full p-md bg-surface-container text-on-surface rounded-lg hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined inline mr-xs">visibility</span>
                    View Portfolio
                </a>
                <a href="{{ route('dashboard.user') }}" class="block w-full p-md bg-surface-container text-on-surface rounded-lg hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined inline mr-xs">pie_chart</span>
                    Manage Budget
                </a>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="lg:col-span-2 p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <h3 class="font-headline-sm text-headline-sm text-on-surface mb-lg">Recent Transactions</h3>
            @if($recentTransactions->count())
                <div class="space-y-md">
                    @foreach($recentTransactions as $transaction)
                        <div class="flex items-center justify-between py-md border-b border-outline-variant last:border-b-0">
                            <div class="flex items-center gap-md">
                                <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary text-sm">
                                        @if($transaction->type === 'income')
                                            trending_up
                                        @elseif($transaction->type === 'expense')
                                            trending_down
                                        @else
                                            swap_horiz
                                        @endif
                                    </span>
                                </div>
                                <div>
                                    <p class="font-body-md text-body-md text-on-surface">{{ $transaction->description }}</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $transaction->transaction_date->format('M d, Y') }}</p>
                                </div>
                            </div>
                            <p class="font-label-lg text-label-lg font-bold">{{ $transaction->type === 'income' ? '+' : '-' }}{{ \App\Helpers\Currency::tzs($transaction->amount) }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-on-surface-variant">No recent transactions</p>
            @endif
        </div>
    </div>
</div>
@endsection
