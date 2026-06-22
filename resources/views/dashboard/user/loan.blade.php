@extends('layouts.app')
@section('title', 'Loan #' . $loan->id . ' | FinancePro')

@push('styles')
<style>
@media print {
    .no-print { display: none !important; }
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
@endpush

@section('content')
<div class="print-area space-y-lg">
    <div class="bg-surface-container-lowest px-margin-mobile md:px-xl py-lg no-print">
        <div class="max-w-container-max mx-auto flex flex-col md:flex-row md:items-center justify-between gap-md">
            <div>
                <div class="flex items-center gap-sm mb-xs">
                    <span class="bg-secondary-container text-on-secondary-container px-sm py-xs rounded-lg text-label-md font-bold">Loan #{{ $loan->id }}</span>
                    <span class="text-on-surface-variant font-label-md">&middot; {{ $loan->created_at->diffForHumans() }}</span>
                </div>
                <h2 class="font-display-md text-display-md text-on-surface tracking-tight">Loan Application Review</h2>
            </div>
            <div class="flex items-center gap-sm">
                <button onclick="window.print()" class="bg-surface-container-high text-on-surface px-lg py-sm rounded-lg font-label-lg hover:bg-surface-container-highest transition-colors flex items-center gap-xs">
                    <span class="material-symbols-outlined">print</span> Print
                </button>
                <a href="{{ url()->previous() }}" class="bg-primary text-on-primary px-lg py-sm rounded-lg font-label-lg shadow-sm hover:opacity-90 transition-all flex items-center gap-xs">
                    <span class="material-symbols-outlined">arrow_back</span> Back
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-container-max mx-auto grid grid-cols-1 lg:grid-cols-12 gap-gutter px-margin-mobile md:px-xl">
        <div class="lg:col-span-7 space-y-lg">
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm">
                <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-md">
                    <div class="flex items-center gap-md">
                        <div class="w-12 h-12 rounded-xl bg-primary-fixed-dim flex items-center justify-center text-on-primary-fixed">
                            <span class="material-symbols-outlined">person</span>
                        </div>
                        <div>
                            <h3 class="font-headline-sm text-headline-sm text-on-surface">Applicant Profile</h3>
                            <p class="text-on-surface-variant font-body-sm">Personal Information</p>
                        </div>
                    </div>
                    @if($loan->status === 'approved')
                        <span class="bg-tertiary/10 text-tertiary px-md py-xs rounded-full text-label-md font-bold flex items-center gap-xs">
                            <span class="material-symbols-outlined text-[16px]">check_circle</span> Approved
                        </span>
                    @elseif($loan->status === 'rejected')
                        <span class="bg-error/10 text-error px-md py-xs rounded-full text-label-md font-bold flex items-center gap-xs">
                            <span class="material-symbols-outlined text-[16px]">cancel</span> Rejected
                        </span>
                    @else
                        <span class="bg-surface-container-high text-on-surface-variant px-md py-xs rounded-full text-label-md font-bold flex items-center gap-xs">
                            <span class="material-symbols-outlined text-[16px]">hourglass_empty</span> {{ ucfirst($loan->status) }}
                        </span>
                    @endif
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-xl">
                    <div class="space-y-md">
                        <div>
                            <label class="block text-on-surface-variant font-label-md mb-xs">Full Name</label>
                            <p class="font-body-md text-body-md text-on-surface font-semibold">{{ $loan->user->fullname }}</p>
                        </div>
                        <div>
                            <label class="block text-on-surface-variant font-label-md mb-xs">Email Address</label>
                            <p class="font-body-md text-body-md text-on-surface">{{ $loan->user->email }}</p>
                        </div>
                        <div class="no-print">
                            <label class="block text-on-surface-variant font-label-md mb-xs">Username</label>
                            <p class="font-body-md text-body-md text-on-surface">{{ '@' . $loan->user->username }}</p>
                        </div>
                    </div>
                    <div class="space-y-md">
                        <div>
                            <label class="block text-on-surface-variant font-label-md mb-xs">Phone Number</label>
                            <p class="font-body-md text-body-md text-on-surface">{{ $loan->user->phone ?? '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-on-surface-variant font-label-md mb-xs">NIDA Number</label>
                            <p class="font-body-md text-body-md text-on-surface tracking-widest">{{ $loan->user->nida_number ?? '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-on-surface-variant font-label-md mb-xs">Date of Birth</label>
                            <p class="font-body-md text-body-md text-on-surface">{{ $loan->user->date_of_birth ? $loan->user->date_of_birth->format('M d, Y') : '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm">
                <div class="flex items-center gap-md mb-lg border-b border-outline-variant pb-md">
                    <div class="w-12 h-12 rounded-xl bg-secondary-container flex items-center justify-center text-on-secondary-container">
                        <span class="material-symbols-outlined">payments</span>
                    </div>
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Loan Details</h3>
                        <p class="text-on-surface-variant font-body-sm">Financial parameters &amp; purpose</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
                    <div class="bg-surface-container-low p-md rounded-lg">
                        <label class="block text-on-secondary-container font-label-md mb-xs">Requested Amount</label>
                        <p class="text-headline-md font-bold text-primary">{{ \App\Helpers\Currency::tzs($loan->amount) }}</p>
                    </div>
                    <div class="bg-surface-container-low p-md rounded-lg">
                        <label class="block text-on-secondary-container font-label-md mb-xs">Duration</label>
                        <p class="text-headline-md font-bold text-on-surface">{{ $loan->tenure_months }} Months</p>
                    </div>
                    <div class="bg-surface-container-low p-md rounded-lg no-print">
                            <label class="block text-on-secondary-container font-label-md mb-xs">Monthly Income</label>
                            <p class="text-headline-md font-bold text-tertiary">{{ $loan->monthly_income ? \App\Helpers\Currency::tzs($loan->monthly_income) : '-' }}</p>
                        </div>
                </div>

                @if($loan->status === 'approved' && $loan->interest_rate)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
                    <div class="p-md bg-tertiary/5 rounded-lg border border-tertiary/20">
                        <label class="block text-on-surface-variant font-label-md mb-xs">Interest Rate</label>
                        <p class="text-headline-md font-bold text-tertiary">{{ $loan->interest_rate }}% APR</p>
                    </div>
                    <div class="p-md bg-primary/5 rounded-lg border border-primary/20">
                        <label class="block text-on-surface-variant font-label-md mb-xs">Total Payable</label>
                        <p class="text-headline-md font-bold text-primary">{{ \App\Helpers\Currency::tzs($loan->total_payable) }}</p>
                    </div>
                    <div class="p-md bg-secondary/5 rounded-lg border border-secondary/20">
                        <label class="block text-on-surface-variant font-label-md mb-xs">Monthly Installment</label>
                        <p class="text-headline-md font-bold text-secondary">{{ \App\Helpers\Currency::tzs($loan->repayment_amount) }}</p>
                    </div>
                </div>
                @endif

                <div>
                    <label class="block text-on-surface-variant font-label-md mb-xs">Loan Purpose</label>
                    <p class="font-body-md text-body-md text-on-surface leading-relaxed">{{ $loan->purpose }}</p>
                </div>

                @if($loan->description)
                <div class="mt-lg no-print">
                    <label class="block text-on-surface-variant font-label-md mb-xs">Additional Notes</label>
                    <p class="font-body-md text-body-md text-on-surface leading-relaxed">{{ $loan->description }}</p>
                </div>
                @endif
            </div>

            @if($loan->status === 'approved' && $loan->repayments->count())
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm no-print">
                <div class="flex items-center gap-md mb-lg border-b border-outline-variant pb-md">
                    <div class="w-12 h-12 rounded-xl bg-tertiary-container flex items-center justify-center text-on-tertiary-container">
                        <span class="material-symbols-outlined">schedule</span>
                    </div>
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Repayment Schedule</h3>
                        <p class="text-on-surface-variant font-body-sm">{{ $loan->repayments->count() }} installments</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-outline-variant">
                                <th class="px-md py-sm font-label-md text-on-surface-variant">#</th>
                                <th class="px-md py-sm font-label-md text-on-surface-variant">Due Date</th>
                                <th class="px-md py-sm font-label-md text-on-surface-variant text-right">Amount</th>
                                <th class="px-md py-sm font-label-md text-on-surface-variant text-right">Paid</th>
                                <th class="px-md py-sm font-label-md text-on-surface-variant">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($loan->repayments as $repayment)
                                <tr>
                                    <td class="px-md py-sm font-body-md text-on-surface">{{ $repayment->installment_number }}</td>
                                    <td class="px-md py-sm text-on-surface-variant">{{ $repayment->due_date->format('M d, Y') }}</td>
                                    <td class="px-md py-sm text-right text-on-surface">{{ \App\Helpers\Currency::tzs($repayment->amount) }}</td>
                                    <td class="px-md py-sm text-right text-on-surface">{{ \App\Helpers\Currency::tzs($repayment->paid_amount) }}</td>
                                    <td class="px-md py-sm">
                                        <span class="px-sm py-xs text-[10px] rounded uppercase tracking-widest font-bold
                                            {{ $repayment->status === 'paid' ? 'bg-tertiary/10 text-tertiary' : ($repayment->status === 'overdue' ? 'bg-error/10 text-error' : 'bg-surface-container-high text-on-surface-variant') }}">
                                            {{ ucfirst($repayment->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="lg:col-span-5 space-y-lg">
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm sticky top-[5.5rem]">
                <h3 class="font-headline-sm text-headline-sm text-on-surface mb-lg">Processing Details</h3>

                <div class="space-y-lg">
                    <div>
                        <label class="block text-on-surface-variant font-label-md mb-xs">Application Status</label>
                        <div class="flex items-center gap-sm">
                            @php
                                $statusColor = match($loan->status) {
                                    'approved' => 'text-tertiary bg-tertiary/10',
                                    'rejected' => 'text-error bg-error/10',
                                    'pending' => 'text-on-surface-variant bg-surface-container-high',
                                    default => 'text-on-surface-variant bg-surface-container-high',
                                };
                            @endphp
                            <span class="px-md py-sm rounded-lg font-label-lg {{ $statusColor }}">
                                <span class="material-symbols-outlined text-sm mr-xs">
                                    {{ $loan->status === 'approved' ? 'check_circle' : ($loan->status === 'rejected' ? 'cancel' : 'hourglass_empty') }}
                                </span>
                                {{ ucfirst($loan->status) }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-on-surface-variant font-label-md mb-xs">Applied On</label>
                        <p class="font-body-md text-on-surface">{{ $loan->created_at->format('F d, Y \a\t H:i') }}</p>
                    </div>

                    @if($loan->approved_at)
                    <div>
                        <label class="block text-on-surface-variant font-label-md mb-xs">Approved On</label>
                        <p class="font-body-md text-on-surface">{{ $loan->approved_at->format('F d, Y \a\t H:i') }}</p>
                    </div>
                    @endif

                    @if($loan->rejected_at)
                    <div>
                        <label class="block text-on-surface-variant font-label-md mb-xs">Rejected On</label>
                        <p class="font-body-md text-on-surface">{{ $loan->rejected_at->format('F d, Y \a\t H:i') }}</p>
                    </div>
                    @endif

                    @if($loan->processor)
                    <div class="pt-lg border-t border-outline-variant">
                        <label class="block text-on-surface-variant font-label-md mb-xs">Processed By</label>
                        <div class="flex items-center gap-md">
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                                <span class="material-symbols-outlined text-primary">verified_user</span>
                            </div>
                            <div>
                                <p class="font-body-md text-on-surface font-semibold">{{ $loan->processor->fullname }}</p>
                                <p class="text-body-sm text-on-surface-variant capitalize">{{ $loan->processor->role }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($loan->amount_paid > 0)
                    <div class="pt-lg border-t border-outline-variant">
                        <label class="block text-on-surface-variant font-label-md mb-xs">Amount Repaid</label>
                        <p class="font-headline-sm text-headline-sm text-tertiary">{{ \App\Helpers\Currency::tzs($loan->amount_paid) }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(request()->has('print'))
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 500);
    });
</script>
@endif
@endpush
