@extends('layouts.app')
@section('title', 'All Processed Loans')

@section('content')
<div class="space-y-lg">
    <div class="mb-2xl flex items-center justify-between">
        <div>
            <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">All Processed Loans</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">Full system-wide view of every approved and rejected loan across all staff</p>
        </div>
        <button onclick="window.print()" class="no-print flex items-center gap-sm px-lg py-sm bg-primary text-on-primary rounded-lg font-label-md hover:opacity-90 transition-all">
            <span class="material-symbols-outlined text-sm">print</span>
            Print Full Report
        </button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-md lg:gap-lg print-area">
        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Approved</p>
            <p class="font-headline-lg text-headline-lg text-tertiary">{{ $totals['approved'] }}</p>
            <p class="text-body-sm text-on-surface-variant mt-xs">{{ \App\Helpers\Currency::tzs($totals['approved_amount']) }}</p>
        </div>
        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Rejected</p>
            <p class="font-headline-lg text-headline-lg text-error">{{ $totals['rejected'] }}</p>
            <p class="text-body-sm text-on-surface-variant mt-xs">{{ \App\Helpers\Currency::tzs($totals['rejected_amount']) }}</p>
        </div>
        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Active Staff</p>
            <p class="font-headline-lg text-headline-lg text-primary">{{ $totals['staff'] }}</p>
        </div>
        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Approval Rate</p>
            @php $totalProcessed = $totals['approved'] + $totals['rejected']; $rate = $totalProcessed > 0 ? round(($totals['approved'] / $totalProcessed) * 100) : 0; @endphp
            <p class="font-headline-lg text-headline-lg text-secondary">{{ $rate }}%</p>
        </div>
        <div class="p-md lg:p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
            <p class="font-label-md text-label-md text-on-surface-variant mb-md">Total Volume</p>
            <p class="font-headline-lg text-headline-lg text-on-surface">{{ \App\Helpers\Currency::tzs($totals['approved_amount'] + $totals['rejected_amount']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-outline-variant shadow-sm p-lg no-print">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-md items-end">
            <div>
                <label class="block font-label-md text-on-surface-variant mb-xs">Search Applicant</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or email..." class="w-full px-md py-sm border border-outline-variant rounded-lg font-body-md">
            </div>
            <div>
                <label class="block font-label-md text-on-surface-variant mb-xs">Status</label>
                <select name="status" class="w-full px-md py-sm border border-outline-variant rounded-lg font-body-md">
                    <option value="">All</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <label class="block font-label-md text-on-surface-variant mb-xs">Processed By</label>
                <select name="processor_id" class="w-full px-md py-sm border border-outline-variant rounded-lg font-body-md">
                    <option value="">All Staff</option>
                    @foreach($processors as $p)
                        <option value="{{ $p->id }}" {{ request('processor_id') == $p->id ? 'selected' : '' }}>{{ $p->fullname }} ({{ $p->role }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-label-md text-on-surface-variant mb-xs">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-md py-sm border border-outline-variant rounded-lg font-body-md">
            </div>
            <div>
                <label class="block font-label-md text-on-surface-variant mb-xs">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-md py-sm border border-outline-variant rounded-lg font-body-md">
            </div>
            <div class="md:col-span-5 flex gap-sm">
                <button type="submit" class="px-lg py-sm bg-primary text-on-primary rounded-lg font-label-md hover:opacity-90 transition-all">Filter</button>
                <a href="{{ route('admin.all-processed') }}" class="px-lg py-sm border border-outline-variant rounded-lg font-label-md text-on-surface hover:bg-surface-container transition-all">Reset</a>
            </div>
        </form>
    </div>

    <div class="space-y-lg print-area">
        <h3 class="font-headline-sm text-headline-sm text-on-surface">Staff Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
            @forelse($processorStats as $staff)
                <div class="bg-white rounded-xl border border-outline-variant shadow-sm p-lg">
                    <div class="flex items-center gap-md mb-lg">
                        <div class="w-10 h-10 rounded-full {{ $staff->total_processed > 0 ? ($staff->approved_count >= $staff->rejected_count ? 'bg-tertiary/10' : 'bg-error/10') : 'bg-surface-container-high' }} flex items-center justify-center">
                            <span class="material-symbols-outlined text-sm {{ $staff->total_processed > 0 ? ($staff->approved_count >= $staff->rejected_count ? 'text-tertiary' : 'text-error') : 'text-on-surface-variant' }}">person</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-body-md text-body-md text-on-surface truncate">{{ $staff->fullname }}</h4>
                            <p class="text-body-sm text-on-surface-variant capitalize">{{ $staff->role }}</p>
                        </div>
                        <div class="text-right">
                            @php $staffRate = $staff->total_processed > 0 ? round(($staff->approved_count / $staff->total_processed) * 100) : 0; @endphp
                            <p class="font-headline-sm text-headline-sm {{ $staffRate >= 50 ? 'text-tertiary' : 'text-error' }}">{{ $staffRate }}%</p>
                            <p class="text-body-sm text-on-surface-variant">approval</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-sm">
                        <div class="p-sm bg-surface-container-low rounded-lg text-center">
                            <p class="font-label-md text-on-surface-variant">Approved</p>
                            <p class="font-headline-sm text-headline-sm text-tertiary mt-xs">{{ $staff->approved_count }}</p>
                            <p class="text-body-sm text-on-surface-variant truncate" title="{{ \App\Helpers\Currency::tzs($staff->approved_amount) }}">{{ \App\Helpers\Currency::tzs($staff->approved_amount) }}</p>
                        </div>
                        <div class="p-sm bg-surface-container-low rounded-lg text-center">
                            <p class="font-label-md text-on-surface-variant">Rejected</p>
                            <p class="font-headline-sm text-headline-sm text-error mt-xs">{{ $staff->rejected_count }}</p>
                            <p class="text-body-sm text-on-surface-variant truncate" title="{{ \App\Helpers\Currency::tzs($staff->rejected_amount) }}">{{ \App\Helpers\Currency::tzs($staff->rejected_amount) }}</p>
                        </div>
                        <div class="p-sm bg-surface-container-low rounded-lg text-center">
                            <p class="font-label-md text-on-surface-variant">Total</p>
                            <p class="font-headline-sm text-headline-sm text-primary mt-xs">{{ $staff->total_processed }}</p>
                            <p class="text-body-sm text-on-surface-variant truncate" title="{{ \App\Helpers\Currency::tzs($staff->approved_amount + $staff->rejected_amount) }}">{{ \App\Helpers\Currency::tzs($staff->approved_amount + $staff->rejected_amount) }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 lg:col-span-3 text-center text-on-surface-variant py-lg">No staff have processed any loans yet.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden print-area">
        <div class="px-xl py-lg border-b border-outline-variant flex items-center justify-between">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Loan Activity Log</h3>
            <span class="text-body-sm text-on-surface-variant">{{ $loans->total() }} records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Applicant</th>
                        <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-right">Amount</th>
                        <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Purpose</th>
                        <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Processed By</th>
                        <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Status</th>
                        <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Processed At</th>
                        <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-center no-print">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse($loans as $loan)
                        <tr class="hover:bg-surface-container transition-colors">
                            <td class="px-xl py-lg">
                                <p class="font-body-md text-on-surface">{{ $loan->user->fullname }}</p>
                                <p class="text-body-sm text-on-surface-variant">{{ $loan->user->email }}</p>
                            </td>
                            <td class="px-xl py-lg text-right font-semibold text-on-surface">{{ \App\Helpers\Currency::tzs($loan->amount) }}</td>
                            <td class="px-xl py-lg text-on-surface-variant max-w-[200px] truncate" title="{{ $loan->purpose }}">{{ $loan->purpose }}</td>
                            <td class="px-xl py-lg">
                                @if($loan->processor)
                                    <div class="flex items-center gap-sm">
                                        <span class="material-symbols-outlined text-sm text-on-surface-variant">verified_user</span>
                                        <span class="text-on-surface">{{ $loan->processor->fullname }}</span>
                                        <span class="text-body-sm text-on-surface-variant">({{ $loan->processor->role }})</span>
                                    </div>
                                @else
                                    <span class="text-on-surface-variant">-</span>
                                @endif
                            </td>
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
                            <td colspan="7" class="px-xl py-lg text-center text-on-surface-variant">No processed loans found.</td>
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
