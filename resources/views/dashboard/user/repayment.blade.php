@extends('layouts.app')
@section('title', 'Repayment Management |FinancePro')

@section('content')
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-md">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface">Repayment Management</h2>
            <p class="text-body-md text-on-surface-variant">Track your loan progress and manage upcoming schedules.
            </p>
        </div>
        <button
            class="flex items-center justify-center gap-sm bg-primary text-on-primary px-xl py-md rounded-lg font-label-lg text-label-lg shadow-sm hover:opacity-90 active:scale-95 transition-all">
            <span class="material-symbols-outlined">payments</span>
            Make Payment
        </button>
    </header>
    <!-- Data Overview Section (Bento Grid) -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-lg">
        <!-- Repayment Progress Card (Large) -->
        <div
            class="md:col-span-4 bg-surface-container-lowest border border-outline-variant rounded-xl p-xl flex flex-col items-center justify-center text-center shadow-sm">
            <h3 class="font-label-lg text-label-lg text-on-surface-variant mb-xl uppercase tracking-wider">Total
                Repayment Progress</h3>
            <div class="relative w-48 h-48 mb-xl">
                <svg class="w-full h-full" viewbox="0 0 100 100">
                    <circle class="text-surface-container stroke-current" cx="50" cy="50" fill="transparent" r="42"
                        stroke-width="8"></circle>
                    <circle class="text-primary stroke-current progress-ring__circle" cx="50" cy="50" fill="transparent"
                        r="42" stroke-linecap="round" stroke-width="8"
                        style="stroke-dasharray: 263.89; stroke-dashoffset: 79.16;"></circle>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="font-display-md text-display-md text-on-surface">70%</span>
                    <span class="text-label-md text-on-surface-variant">Completed</span>
                </div>
            </div>
            <div class="flex flex-col gap-xs">
                <p class="text-body-sm text-on-surface-variant">Loan Ref: <span
                        class="font-bold text-on-surface">#LN-8842-X</span></p>
                <p class="text-label-md text-tertiary font-bold px-md py-xs bg-tertiary-container/20 rounded-full">
                    On Track</p>
            </div>
        </div>
        <!-- Financial Metrics (Small Cards Column) -->
        <div class="md:col-span-8 grid grid-cols-1 md:grid-cols-2 gap-lg">
            <!-- Outstanding Balance -->
            <div
                class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg flex flex-col justify-between shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-label-lg text-on-surface-variant font-medium">Outstanding Balance</p>
                        <h4 class="font-display-md text-display-md text-on-surface mt-xs">$12,450.00</h4>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-error-container/30 flex items-center justify-center text-error">
                        <span class="material-symbols-outlined">account_balance_wallet</span>
                    </div>
                </div>
                <div
                    class="mt-lg pt-md border-t border-outline-variant flex items-center gap-xs text-on-surface-variant text-body-sm">
                    <span class="material-symbols-outlined text-sm text-primary">info</span>
                    Principal + Accrued Interest
                </div>
            </div>
            <!-- Next Payment -->
            <div
                class="bg-primary-container/10 border border-primary/20 rounded-xl p-lg flex flex-col justify-between shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-label-lg text-primary font-medium">Next Payment</p>
                        <h4 class="font-display-md text-display-md text-primary mt-xs">$850.00</h4>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined">event</span>
                    </div>
                </div>
                <div class="mt-lg pt-md border-t border-primary/20 flex flex-col">
                    <p class="text-body-sm text-on-surface-variant">Due Date</p>
                    <p class="font-bold text-on-surface text-body-lg">October 15, 2024</p>
                </div>
            </div>
            <!-- Total Paid -->
            <div
                class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg flex flex-col justify-between shadow-sm md:col-span-2">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-lg">
                    <div class="flex items-center gap-lg">
                        <div
                            class="w-14 h-14 rounded-xl bg-tertiary-container/20 flex items-center justify-center text-tertiary">
                            <span class="material-symbols-outlined" style="font-size: 32px;">verified</span>
                        </div>
                        <div>
                            <p class="text-label-lg text-on-surface-variant font-medium">Total Paid to Date</p>
                            <h4 class="font-headline-lg text-headline-lg text-on-surface mt-xs">$28,550.00</h4>
                        </div>
                    </div>
                    <div class="flex items-center gap-xl md:border-l border-outline-variant md:pl-xl">
                        <div>
                            <p class="text-label-md text-on-surface-variant">Principal</p>
                            <p class="font-bold text-on-surface">$24,000.00</p>
                        </div>
                        <div>
                            <p class="text-label-md text-on-surface-variant">Interest</p>
                            <p class="font-bold text-on-surface">$4,550.00</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Schedule Table -->
    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden">
        <div class="p-lg border-b border-outline-variant flex items-center justify-between">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Repayment Schedule</h3>
            <div class="flex gap-sm">
                <button
                    class="px-md py-sm bg-surface-container-low text-on-surface border border-outline-variant rounded-lg font-label-md text-label-md hover:bg-surface-container-high transition-colors">
                    Download PDF
                </button>
                <button
                    class="px-md py-sm bg-surface-container-low text-on-surface border border-outline-variant rounded-lg font-label-md text-label-md hover:bg-surface-container-high transition-colors">
                    Filter
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container-low">
                    <tr>
                        <th class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant">Installment No.
                        </th>
                        <th class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant">Due Date</th>
                        <th class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant">Repayment Amount
                        </th>
                        <th class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant">Status</th>
                        <th class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant text-right">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <!-- Paid Row -->
                    <tr class="hover:bg-surface-container-low transition-colors group">
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">#08</td>
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">Sep 15, 2024</td>
                        <td class="px-lg py-md font-bold text-body-md text-on-surface">$850.00</td>
                        <td class="px-lg py-md">
                            <span
                                class="inline-flex items-center gap-xs px-md py-xs rounded-full bg-tertiary-container/20 text-tertiary font-bold text-label-md">
                                <span class="material-symbols-outlined text-sm"
                                    style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                Paid
                            </span>
                        </td>
                        <td class="px-lg py-md text-right">
                            <button class="text-primary hover:underline font-label-md text-label-md">View
                                Receipt</button>
                        </td>
                    </tr>
                    <!-- Upcoming/Current Row -->
                    <tr class="bg-primary/5 hover:bg-primary/10 transition-colors group">
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">#09</td>
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">Oct 15, 2024</td>
                        <td class="px-lg py-md font-bold text-body-md text-on-surface">$850.00</td>
                        <td class="px-lg py-md">
                            <span
                                class="inline-flex items-center gap-xs px-md py-xs rounded-full bg-primary/10 text-primary font-bold text-label-md">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                Upcoming
                            </span>
                        </td>
                        <td class="px-lg py-md text-right">
                            <button
                                class="bg-primary text-on-primary px-md py-sm rounded-lg font-label-md text-label-md shadow-sm">Pay
                                Now</button>
                        </td>
                    </tr>
                    <!-- Future Row -->
                    <tr class="hover:bg-surface-container-low transition-colors group">
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">#10</td>
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">Nov 15, 2024</td>
                        <td class="px-lg py-md font-bold text-body-md text-on-surface">$850.00</td>
                        <td class="px-lg py-md">
                            <span
                                class="inline-flex items-center gap-xs px-md py-xs rounded-full bg-surface-container-high text-on-surface-variant font-bold text-label-md">
                                <span class="material-symbols-outlined text-sm">pending</span>
                                Scheduled
                            </span>
                        </td>
                        <td class="px-lg py-md text-right">
                            <button
                                class="text-on-surface-variant hover:text-on-surface font-label-md text-label-md">Details</button>
                        </td>
                    </tr>
                    <!-- Future Row -->
                    <tr class="hover:bg-surface-container-low transition-colors group">
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">#11</td>
                        <td class="px-lg py-md font-body-md text-body-md text-on-surface">Dec 15, 2024</td>
                        <td class="px-lg py-md font-bold text-body-md text-on-surface">$850.00</td>
                        <td class="px-lg py-md">
                            <span
                                class="inline-flex items-center gap-xs px-md py-xs rounded-full bg-surface-container-high text-on-surface-variant font-bold text-label-md">
                                <span class="material-symbols-outlined text-sm">pending</span>
                                Scheduled
                            </span>
                        </td>
                        <td class="px-lg py-md text-right">
                            <button
                                class="text-on-surface-variant hover:text-on-surface font-label-md text-label-md">Details</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="p-lg bg-surface-container-low flex items-center justify-between">
            <span class="text-body-sm text-on-surface-variant">Showing 4 of 36 installments</span>
            <div class="flex gap-sm">
                <button
                    class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button
                    class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
        </div>
    </section>
    <!-- Dynamic Visualization (Floating Card) -->
    <div class="relative w-full h-[400px] rounded-2xl overflow-hidden shadow-xl border border-outline-variant">

        <div
            class="absolute inset-0 bg-gradient-to-t from-on-surface/80 via-transparent to-transparent flex items-end p-2xl">
            <div class="max-w-2xl text-white">
                <h4 class="font-display-md text-display-md mb-md">Future Projections</h4>
                <p class="text-body-lg opacity-90">Based on your current repayment velocity, you are estimated to
                    clear your loan <span class="font-bold underline decoration-tertiary underline-offset-4">4
                        months early</span>. This would save you approximately <span class="font-bold">$1,200</span>
                    in cumulative interest.</p>
                <div class="mt-xl flex gap-lg">
                    <button
                        class="bg-white text-on-surface font-label-lg text-label-lg px-xl py-md rounded-lg shadow-lg hover:scale-105 active:scale-95 transition-all">
                        Review Savings
                    </button>
                    <button
                        class="bg-transparent border-2 border-white/40 text-white font-label-lg text-label-lg px-xl py-md rounded-lg hover:bg-white/10 transition-all">
                        Recalculate Plan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation for Mobile -->
    <nav
        class="md:hidden fixed bottom-0 left-0 right-0 bg-surface-container-lowest border-t border-outline-variant flex justify-around items-center h-16 z-50">
        <a class="flex flex-col items-center gap-1 text-on-surface-variant" href="#">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="text-[10px] font-bold">Dashboard</span>
        </a>
        <a class="flex flex-col items-center gap-1 text-primary" href="#">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
            <span class="text-[10px] font-bold">Pay</span>
        </a>
        <a class="flex flex-col items-center gap-1 text-on-surface-variant" href="#">
            <span class="material-symbols-outlined">history_edu</span>
            <span class="text-[10px] font-bold">Loans</span>
        </a>
        <a class="flex flex-col items-center gap-1 text-on-surface-variant" href="#">
            <span class="material-symbols-outlined">settings</span>
            <span class="text-[10px] font-bold">Settings</span>
        </a>
    </nav>
    <script>
        // Micro-interaction for Progress Circle
        document.addEventListener('DOMContentLoaded', () => {
            const circle = document.querySelector('.progress-ring__circle');
            const radius = circle.r.baseVal.value;
            const circumference = radius * 2 * Math.PI;

            circle.style.strokeDasharray = `${circumference} ${circumference}`;
            circle.style.strokeDashoffset = circumference;

            function setProgress(percent) {
                const offset = circumference - (percent / 100 * circumference);
                circle.style.strokeDashoffset = offset;
            }

            // Animate on load
            setTimeout(() => setProgress(70), 500);
        });

        // Simple smooth scroll and interaction feedback
        document.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', function (e) {
                let ripple = document.createElement('span');
                ripple.classList.add('ripple');
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 1000);
            });
        });
    </script>
@endsection