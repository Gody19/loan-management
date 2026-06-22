@extends('layouts.app')
@section('page-title', 'Loan History')
@section('title', 'FinancePro | Loan History')
@section('content')
    <section class="space-y-lg max-w-container-max mx-auto w-full">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-lg">
            <div class="md:col-span-2 bg-primary-container text-on-primary-container p-xl rounded-xl relative overflow-hidden flex flex-col justify-between min-h-[160px]">
                <div class="relative z-10">
                    <h3 class="font-headline-md text-headline-md mb-xs">Track Your Growth</h3>
                    <p class="font-body-md text-body-md opacity-90 max-w-md">Review your borrowing history and manage repayments effectively.</p>
                </div>
                <div class="absolute right-0 bottom-0 opacity-20 transform translate-x-1/4 translate-y-1/4">
                    <span class="material-symbols-outlined text-[120px]">analytics</span>
                </div>
            </div>
            <div class="bg-surface-container-lowest p-xl rounded-xl border border-outline-variant shadow-sm flex flex-col justify-center">
                <p class="text-on-surface-variant font-label-md text-label-md uppercase tracking-widest mb-sm">Total Active Loans</p>
                <div class="flex items-baseline gap-sm">
                    <span class="font-display-md text-display-md text-primary">{{ \App\Helpers\Currency::tzs($loans->whereIn('status', ['approved', 'pending'])->sum('amount')) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-md no-print">
            <div class="flex-1 flex flex-col md:flex-row gap-md">
                <div class="relative flex-1 max-w-md">
                    <span class="material-symbols-outlined absolute left-md top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                    <input class="w-full h-12 pl-12 pr-md bg-surface-container-low border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-body-sm" placeholder="Search by Loan ID or Type..." type="text" />
                </div>
                <div class="relative w-full md:w-48">
                    <select class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-body-sm appearance-none cursor-pointer">
                        <option value="">Filter by Status</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="overdue">Overdue</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-md top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant">expand_more</span>
                </div>
            </div>
            <button onclick="window.print()" class="flex items-center gap-sm px-lg py-sm bg-primary text-on-primary rounded-lg font-label-md hover:opacity-90 transition-all">
                <span class="material-symbols-outlined text-sm">print</span>
                Print
            </button>
        </div>

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden print-area">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Purpose</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-right">Amount</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Tenure</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Status</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Date</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-center no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @forelse($loans as $loan)
                            <tr class="hover:bg-surface-container transition-colors group">
                                <td class="px-xl py-lg">
                                    <div class="flex items-center gap-sm">
                                        <span class="font-body-md text-on-surface">{{ $loan->purpose }}</span>
                                    </div>
                                </td>
                                <td class="px-xl py-lg text-right font-semibold text-on-surface">{{ \App\Helpers\Currency::tzs($loan->amount) }}</td>
                                <td class="px-xl py-lg text-on-surface-variant">{{ $loan->tenure_months }} Months</td>
                                <td class="px-xl py-lg">
                                    @php
                                        $badgeClass = match($loan->status) {
                                            'approved' => 'bg-tertiary-fixed-dim/20 text-tertiary',
                                            'pending' => 'bg-surface-container-high text-on-surface-variant',
                                            'rejected' => 'bg-error-container text-error',
                                            'completed' => 'bg-secondary-fixed/50 text-secondary',
                                            'defaulted' => 'bg-error-container text-error',
                                            default => 'bg-surface-container-high text-on-surface-variant',
                                        };
                                    @endphp
                                    <span class="px-sm py-xs {{ $badgeClass }} font-label-md text-[10px] rounded uppercase tracking-widest font-bold">{{ ucfirst($loan->status) }}</span>
                                </td>
                                <td class="px-xl py-lg text-on-surface-variant text-body-sm">{{ $loan->created_at->format('M d, Y') }}</td>
                                <td class="px-xl py-lg no-print">
                                    <div class="flex items-center justify-center gap-sm">
                                        <a href="{{ route('loans.show', $loan) }}" class="px-md py-sm bg-primary/10 text-primary rounded-lg font-label-md hover:bg-primary/20 transition-all flex items-center gap-xs text-xs">
                                            <span class="material-symbols-outlined text-sm">visibility</span> View
                                        </a>
                                        <a href="{{ route('loans.show', $loan) . '?print=1' }}" target="_blank" class="px-md py-sm bg-surface-container-high text-on-surface rounded-lg font-label-md hover:bg-surface-container-highest transition-all flex items-center gap-xs text-xs">
                                            <span class="material-symbols-outlined text-sm">print</span> Print
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-xl py-lg text-center text-on-surface-variant">No loan applications yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-xl py-md bg-surface-container-low border-t border-outline-variant">
                {{ $loans->links() }}
            </div>
        </div>
    </section>

    <button class="md:hidden fixed bottom-margin-mobile right-margin-mobile w-14 h-14 bg-primary text-on-primary rounded-full shadow-2xl flex items-center justify-center z-50">
        <span class="material-symbols-outlined">add</span>
    </button>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('tbody tr');
            const searchInput = document.querySelector('input[type="text"]');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase();
                    rows.forEach(row => {
                        const text = row.innerText.toLowerCase();
                        row.style.display = text.includes(term) ? '' : 'none';
                    });
                });
            }
        });
    </script>
@endsection
