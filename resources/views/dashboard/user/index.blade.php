@extends('layouts.app')

@section('title', 'FinancePro | Customer Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <!-- Welcome Section -->
    <section class="relative overflow-hidden rounded-xl bg-primary p-xl text-on-primary">
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-lg">
            <div>
                <h2 class="font-display-md text-display-md text-on-primary">Welcome back, {{ Auth::user()->username }}.</h2>
                <p class="font-body-lg text-body-lg text-on-primary/80 mt-base">Your financial status is looking strong today. You have one payment due this week.</p>
            </div>
            <div class="flex gap-md w-full md:w-auto">
                <button class="flex-1 md:flex-none bg-on-primary text-primary font-bold px-xl py-md rounded-lg shadow-sm hover:bg-surface-container-lowest transition-all active:scale-95">
                    Apply for New Loan
                </button>
                <!--<button class="flex-1 md:flex-none border border-on-primary/30 text-on-primary font-bold px-xl py-md rounded-lg hover:bg-on-primary/10 transition-all active:scale-95">
                    Make a Payment
                </button>-->
            </div>
        </div>
        
        <!-- Abstract background pattern -->
        <div class="absolute right-0 top-0 h-full w-1/3 opacity-10 pointer-events-none">
            <svg class="h-full w-full" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <path d="M47.7,-62.4C61.4,-55.8,71.8,-41.2,76.5,-25.2C81.2,-9.2,80.1,8.3,73.4,23.5C66.7,38.7,54.3,51.6,39.6,59.3C24.9,67,7.8,69.5,-9,67.3C-25.8,65.1,-42.2,58.3,-54.6,46.5C-67,34.7,-75.4,17.9,-75.1,1.4C-74.8,-15.1,-65.7,-31.2,-53.1,-41.8C-40.5,-52.4,-24.3,-57.5,-7.9,-62.3C8.5,-67.1,16.9,-71.5,30.3,-69.1C43.7,-66.7,34,-5.4,47.7,-62.4Z" fill="#FFFFFF" transform="translate(100 100)"></path>
            </svg>
        </div>
    </section>
    
    <!-- KPI Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-lg">
        <!-- Total Borrowed -->
        <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <span class="text-on-surface-variant font-label-lg text-label-lg">Total Borrowed</span>
                <span class="material-symbols-outlined text-primary bg-primary-container/10 p-sm rounded-lg">account_balance_wallet</span>
            </div>
            <div class="mt-md">
                <p class="font-headline-lg text-headline-lg text-on-surface">$24,500.00</p>
                <p class="text-tertiary text-xs font-semibold flex items-center mt-xs">
                    <span class="material-symbols-outlined text-sm mr-xs">trending_up</span>
                    +12% from last month
                </p>
            </div>
        </div>
        
        <!-- Active Loans -->
        <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <span class="text-on-surface-variant font-label-lg text-label-lg">Active Loans</span>
                <span class="material-symbols-outlined text-primary bg-primary-container/10 p-sm rounded-lg">contract</span>
            </div>
            <div class="mt-md">
                <p class="font-headline-lg text-headline-lg text-on-surface">3</p>
                <p class="text-on-surface-variant text-xs font-medium mt-xs">Across 2 lenders</p>
            </div>
        </div>
        
        <!-- Next Payment -->
        <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <span class="text-on-surface-variant font-label-lg text-label-lg">Next Payment</span>
                <span class="material-symbols-outlined text-error bg-error-container/20 p-sm rounded-lg">event</span>
            </div>
            <div class="mt-md">
                <p class="font-headline-lg text-headline-lg text-on-surface">$1,240.00</p>
                <p class="text-error text-xs font-semibold mt-xs">Due in 4 days</p>
            </div>
        </div>
        
        <!-- Loan Limit -->
        <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <span class="text-on-surface-variant font-label-lg text-label-lg">Loan Limit</span>
                <span class="material-symbols-outlined text-tertiary bg-tertiary-container/10 p-sm rounded-lg">verified_user</span>
            </div>
            <div class="mt-md">
                <p class="font-headline-lg text-headline-lg text-on-surface">$50,000.00</p>
                <div class="w-full bg-surface-container-high h-1.5 rounded-full mt-sm overflow-hidden">
                    <div class="bg-tertiary h-full rounded-full" style="width: 49%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-xl">
        <!-- Recent Activity Table -->
        <div class="lg:col-span-2 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm">
            <div class="p-lg border-b border-outline-variant flex items-center justify-between">
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Recent Activity</h3>
                <button class="text-primary font-label-lg text-label-lg hover:underline transition-all">View All</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface-container-low">
                        <tr>
                            <th class="px-lg py-md text-on-surface-variant font-label-lg text-label-lg">Reference</th>
                            <th class="px-lg py-md text-on-surface-variant font-label-lg text-label-lg">Type</th>
                            <th class="px-lg py-md text-on-surface-variant font-label-lg text-label-lg">Status</th>
                            <th class="px-lg py-md text-on-surface-variant font-label-lg text-label-lg">Amount</th>
                            <th class="px-lg py-md text-on-surface-variant font-label-lg text-label-lg text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @foreach($recentActivities ?? [] as $activity)
                        <tr class="hover:bg-surface-container/50 transition-colors">
                            <td class="px-lg py-md font-semibold text-on-surface">{{-- $activity->reference --}}</td>
                            <td class="px-lg py-md text-on-surface-variant">{{-- $activity->type --}}</td>
                            <td class="px-lg py-md">
                                <span class="px-sm py-base rounded-full {{-- $activity->status_class --}} text-xs font-bold uppercase tracking-tighter">
                                    {{-- $activity->status --}}
                                </span>
                            </td>
                            <td class="px-lg py-md text-on-surface font-medium">${{-- number_format($activity->amount,2) --}}</td>
                            <td class="px-lg py-md text-on-surface-variant text-right">{{-- $activity->date->format('Md,Y') --}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Repayment Progress Card -->
        <div class="flex flex-col gap-lg">
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm">
                <h3 class="font-headline-sm text-headline-sm text-on-surface mb-lg">Repayment Progress</h3>
                <div class="space-y-xl">
                    @foreach($loans ?? [] as $loan)
                    <div class="space-y-sm">
                        <div class="flex justify-between items-end">
                            <div>
                                <p class="font-label-lg text-label-lg text-on-surface">{{-- $loan->name --}}</p>
                                <p class="text-xs text-on-surface-variant">Ref: {{-- $loan->reference --}}</p>
                            </div>
                            <p class="text-on-surface font-bold">{{-- $loan->progress --}}%</p>
                        </div>
                        <div class="w-full bg-surface-container-high h-2.5 rounded-full overflow-hidden">
                            <div class="bg-primary h-full rounded-full" style="width: {{-- $loan->progress --}}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-on-surface-variant">
                            <span>${{-- number_format($loan->paid,2) --}} paid</span>
                            <span>${{-- number_format($loan->total,2) --}} total</span>
                        </div>
                    </div>
                    @endforeach
                    
                    <button class="w-full border border-primary text-primary font-bold py-sm rounded-lg hover:bg-primary/5 transition-colors">
                        Detailed Amortization
                    </button>
                </div>
            </div>
            
            <!-- Quick Actions Contextual Card -->
            <div class="bg-primary-container p-lg rounded-xl shadow-sm text-on-primary-container">
                <div class="flex items-start gap-md">
                    <div class="bg-white/20 p-sm rounded-lg">
                        <span class="material-symbols-outlined text-on-primary-container">insights</span>
                    </div>
                    <div>
                        <h4 class="font-bold mb-xs">Optimization Tip</h4>
                        <p class="text-sm opacity-90">Consolidate your active loans to reduce your monthly interest rate by up to 2.4%.</p>
                        <button class="mt-md text-sm font-bold underline underline-offset-4 decoration-2">Learn more</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection