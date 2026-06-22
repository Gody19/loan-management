@extends('layouts.app')
@section('title', 'My Processing History')
@section('content')
    <div class="space-y-lg">
        <div class="mb-2xl flex items-center justify-between">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">My Processing History</h1>
                <p class="font-body-md text-body-md text-on-surface-variant">Loans you have approved or rejected</p>
            </div>
            <button onclick="window.print()" class="no-print flex items-center gap-sm px-lg py-sm bg-primary text-on-primary rounded-lg font-label-md hover:opacity-90 transition-all">
                <span class="material-symbols-outlined text-sm">print</span>
                Print
            </button>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-md lg:gap-lg print-area">
            <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
                <p class="font-label-md text-label-md text-on-surface-variant mb-md">Approved</p>
                <p class="font-headline-lg text-headline-lg text-tertiary">{{ $summary['approved'] }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">{{ \App\Helpers\Currency::tzs($summary['approved_amount']) }}</p>
            </div>
            <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
                <p class="font-label-md text-label-md text-on-surface-variant mb-md">Rejected</p>
                <p class="font-headline-lg text-headline-lg text-error">{{ $summary['rejected'] }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">{{ \App\Helpers\Currency::tzs($summary['rejected_amount']) }}</p>
            </div>
            <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
                <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Processed</p>
                <p class="font-headline-lg text-headline-lg text-primary">{{ $summary['approved'] + $summary['rejected'] }}</p>
            </div>
            <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
                <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Value</p>
                <p class="font-headline-lg text-headline-lg text-secondary">{{ \App\Helpers\Currency::tzs($summary['approved_amount'] + $summary['rejected_amount']) }}</p>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden print-area">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Applicant</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-right">Amount</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Purpose</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Tenure</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Status</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Processed At</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-center no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @forelse($loans as $loan)
                            <tr class="hover:bg-surface-container transition-colors">
                                <td class="px-xl py-lg">
                                    <div>
                                        <p class="font-body-md text-on-surface">{{ $loan->user->fullname }}</p>
                                        <p class="text-body-sm text-on-surface-variant">{{ $loan->user->email }}</p>
                                    </div>
                                </td>
                                <td class="px-xl py-lg text-right font-semibold text-on-surface">{{ \App\Helpers\Currency::tzs($loan->amount) }}</td>
                                <td class="px-xl py-lg text-on-surface-variant">{{ $loan->purpose }}</td>
                                <td class="px-xl py-lg text-on-surface-variant">{{ $loan->tenure_months }} months</td>
                                <td class="px-xl py-lg">
                                    @if($loan->status === 'approved')
                                        <span class="inline-flex items-center gap-xs px-sm py-xs bg-tertiary/10 text-tertiary rounded-full font-label-md text-sm">
                                            <span class="material-symbols-outlined text-sm">check_circle</span> Approved
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-xs px-sm py-xs bg-error/10 text-error rounded-full font-label-md text-sm">
                                            <span class="material-symbols-outlined text-sm">cancel</span> Rejected
                                        </span>
                                    @endif
                                </td>
                                <td class="px-xl py-lg text-on-surface-variant text-body-sm">
                                    {{ ($loan->approved_at ?? $loan->rejected_at ?? $loan->updated_at)->format('M d, Y H:i') }}
                                </td>
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
                                <td colspan="7" class="px-xl py-lg text-center text-on-surface-variant">No processed loans yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-xl py-md bg-surface-container-low border-t border-outline-variant no-print">
                {{ $loans->links() }}
            </div>
        </div>
    </div>
@endsection
