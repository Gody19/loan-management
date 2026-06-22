@extends('layouts.app')
@section('title', 'My Loan History')

@section('content')
<div class="space-y-lg">
    <div class="mb-2xl">
        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">My Loan History</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">All your loan applications and payment schedules</p>
    </div>

    @forelse($loans as $loan)
        <div class="bg-white rounded-xl border border-outline-variant shadow-sm overflow-hidden">
            <button onclick="toggleLoan({{ $loan->id }})" class="w-full text-left p-lg hover:bg-surface-container-low transition-colors">
                <div class="flex items-center justify-between gap-md">
                    <div class="flex items-center gap-md min-w-0">
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-primary">account_balance</span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-headline-sm text-headline-sm text-on-surface truncate">{{ $loan->purpose }}</h3>
                            <p class="text-body-sm text-on-surface-variant">Applied {{ $loan->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-sm shrink-0">
                        @php
                            $badgeClass = match($loan->status) {
                                'approved' => 'bg-tertiary/10 text-tertiary',
                                'pending' => 'bg-surface-container-high text-on-surface-variant',
                                'rejected' => 'bg-error/10 text-error',
                                'completed' => 'bg-secondary-fixed/50 text-secondary',
                                'defaulted' => 'bg-error-container text-error',
                                default => 'bg-surface-container-high text-on-surface-variant',
                            };
                        @endphp
                        <span class="px-md py-xs rounded-full font-label-md text-sm {{ $badgeClass }}">{{ ucfirst($loan->status) }}</span>
                        <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-300" id="icon-{{ $loan->id }}">expand_more</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-md mt-md pt-md border-t border-outline-variant/50">
                    <div>
                        <p class="font-label-md text-on-surface-variant">Amount Applied</p>
                        <p class="font-headline-sm text-headline-sm text-on-surface">{{ \App\Helpers\Currency::tzs($loan->amount) }}</p>
                    </div>
                    <div>
                        <p class="font-label-md text-on-surface-variant">Total Payable (incl. interest)</p>
                        <p class="font-headline-sm text-headline-sm text-primary">{{ $loan->total_payable ? \App\Helpers\Currency::tzs($loan->total_payable) : '-' }}</p>
                    </div>
                    <div>
                        <p class="font-label-md text-on-surface-variant">Amount Paid</p>
                        <p class="font-headline-sm text-headline-sm text-tertiary">{{ $loan->amount_paid ? \App\Helpers\Currency::tzs($loan->amount_paid) : 'TZS 0' }}</p>
                    </div>
                </div>
            </button>

            <div id="body-{{ $loan->id }}" class="hidden border-t border-outline-variant">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-md p-lg bg-surface-container-low">
                    <div>
                        <p class="font-label-md text-on-surface-variant">Tenure</p>
                        <p class="font-body-md text-on-surface">{{ $loan->tenure_months }} months</p>
                    </div>
                    <div>
                        <p class="font-label-md text-on-surface-variant">Interest Rate</p>
                        <p class="font-body-md text-on-surface">{{ $loan->interest_rate ? $loan->interest_rate . '% APR' : '-' }}</p>
                    </div>
                    <div>
                        <p class="font-label-md text-on-surface-variant">Monthly Installment</p>
                        <p class="font-body-md text-on-surface">{{ $loan->repayment_amount ? \App\Helpers\Currency::tzs($loan->repayment_amount) : '-' }}</p>
                    </div>
                    <div>
                        <p class="font-label-md text-on-surface-variant">Remaining Balance</p>
                        <p class="font-body-md text-on-surface">{{ $loan->total_payable ? \App\Helpers\Currency::tzs(max($loan->total_payable - $loan->amount_paid, 0)) : '-' }}</p>
                    </div>
                </div>

                @if($loan->status === 'approved' && $loan->repayments->count())
                    <div class="p-lg">
                        <h4 class="font-label-lg text-label-lg text-on-surface mb-md">Repayment Schedule</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-outline-variant">
                                        <th class="px-md py-sm font-label-md text-on-surface-variant">#</th>
                                        <th class="px-md py-sm font-label-md text-on-surface-variant">Due Date</th>
                                        <th class="px-md py-sm font-label-md text-on-surface-variant text-right">Amount</th>
                                        <th class="px-md py-sm font-label-md text-on-surface-variant text-right">Paid</th>
                                        <th class="px-md py-sm font-label-md text-on-surface-variant text-right">Remaining</th>
                                        <th class="px-md py-sm font-label-md text-on-surface-variant">Status</th>
                                        <th class="px-md py-sm font-label-md text-on-surface-variant text-center no-print">Pay</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @foreach($loan->repayments as $repayment)
                                        <tr class="{{ $repayment->due_date->isPast() && $repayment->status !== 'paid' ? 'bg-error/5' : '' }}">
                                            <td class="px-md py-sm font-body-md text-on-surface">{{ $repayment->installment_number }}</td>
                                            <td class="px-md py-sm text-on-surface-variant">{{ $repayment->due_date->format('M d, Y') }}</td>
                                            <td class="px-md py-sm text-right text-on-surface">{{ \App\Helpers\Currency::tzs($repayment->amount) }}</td>
                                            <td class="px-md py-sm text-right text-on-surface">{{ \App\Helpers\Currency::tzs($repayment->paid_amount) }}</td>
                                            <td class="px-md py-sm text-right text-on-surface">{{ \App\Helpers\Currency::tzs(max($repayment->amount - $repayment->paid_amount, 0)) }}</td>
                                            <td class="px-md py-sm">
                                                <span class="px-sm py-xs text-[10px] rounded uppercase tracking-widest font-bold
                                                    {{ $repayment->status === 'paid' ? 'bg-tertiary/10 text-tertiary' : ($repayment->status === 'overdue' ? 'bg-error/10 text-error' : 'bg-surface-container-high text-on-surface-variant') }}">
                                                    {{ ucfirst($repayment->status) }}
                                                </span>
                                            </td>
                                            <td class="px-md py-sm text-center no-print">
                                                @if($repayment->status !== 'paid')
                                                    <button onclick="openPaymentModal({{ $repayment->id }}, '{{ $repayment->amount - $repayment->paid_amount }}')" class="px-md py-sm bg-primary text-on-primary rounded-lg font-label-md hover:opacity-90 transition-all text-xs">
                                                        Pay
                                                    </button>
                                                @else
                                                    <span class="text-tertiary font-label-md text-xs">&checkmark;</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif($loan->status === 'approved')
                    <div class="p-lg text-center text-on-surface-variant">No repayment schedule generated yet.</div>
                @endif

                <div class="px-lg pb-lg flex gap-sm no-print">
                    <a href="{{ route('loans.show', $loan) }}" class="px-md py-sm bg-primary/10 text-primary rounded-lg font-label-md hover:bg-primary/20 transition-all flex items-center gap-xs text-xs">
                        <span class="material-symbols-outlined text-sm">visibility</span> View Details
                    </a>
                    <a href="{{ route('loans.show', $loan) . '?print=1' }}" target="_blank" class="px-md py-sm bg-surface-container-high text-on-surface rounded-lg font-label-md hover:bg-surface-container-highest transition-all flex items-center gap-xs text-xs">
                        <span class="material-symbols-outlined text-sm">print</span> Print
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-outline-variant shadow-sm p-lg text-center text-on-surface-variant">
            <p class="font-headline-sm text-headline-sm text-on-surface-variant mb-sm">No loan applications yet</p>
            <p class="text-body-md">Apply for your first loan to get started.</p>
            <a href="{{ route('loans.create') }}" class="inline-block mt-lg px-lg py-sm bg-primary text-on-primary rounded-lg font-label-md hover:opacity-90 transition-all">Apply Now</a>
        </div>
    @endforelse

    <div class="no-print">
        {{ $loans->links() }}
    </div>
</div>

<div id="payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-md">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-lg">
        <div class="flex items-center justify-between mb-lg">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Make Payment</h3>
            <button onclick="closePaymentModal()" class="p-1 hover:bg-surface-container rounded-lg transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="payment-form" method="POST" class="space-y-lg">
            @csrf
            <div>
                <label class="block font-label-md text-on-surface-variant mb-xs">Amount (TZS)</label>
                <p class="font-headline-md text-headline-md text-primary" id="modal-amount">TZS 0</p>
            </div>
            <div>
                <label for="phone" class="block font-label-md text-on-surface-variant mb-xs">Mobile Money Phone Number</label>
                <input type="tel" name="phone" id="phone" value="{{ auth()->user()->phone }}" required
                    class="w-full px-md py-sm border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all"
                    placeholder="255712345678">
                <p class="text-body-sm text-on-surface-variant mt-xs">Enter your TZN mobile money number (e.g. 255712345678)</p>
            </div>
            <button type="submit" class="w-full py-sm bg-primary text-on-primary rounded-lg font-label-md hover:opacity-90 transition-all flex items-center justify-center gap-sm">
                <span class="material-symbols-outlined text-sm">paid</span>
                Pay Now
            </button>
        </form>
    </div>
</div>

<script>
function toggleLoan(id) {
    const body = document.getElementById('body-' + id);
    const icon = document.getElementById('icon-' + id);
    body.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

function openPaymentModal(repaymentId, amount) {
    document.getElementById('modal-amount').textContent = 'TZS ' + Number(amount).toLocaleString();
    document.getElementById('payment-form').action = '/payments/' + repaymentId + '/pay';
    document.getElementById('payment-modal').classList.remove('hidden');
    document.getElementById('payment-modal').classList.add('flex');
}

function closePaymentModal() {
    document.getElementById('payment-modal').classList.add('hidden');
    document.getElementById('payment-modal').classList.remove('flex');
}

document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('loan')) {
        const id = params.get('loan');
        const body = document.getElementById('body-' + id);
        const icon = document.getElementById('icon-' + id);
        if (body) {
            body.classList.remove('hidden');
            if (icon) icon.classList.add('rotate-180');
        }
    }

    @if(session('success'))
        setTimeout(function() {
            const alert = document.createElement('div');
            alert.className = 'fixed top-4 right-4 z-50 px-lg py-md bg-tertiary text-on-tertiary rounded-lg shadow-lg font-label-md';
            alert.textContent = '{{ session('success') }}';
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }, 200);
    @elseif(session('error'))
        setTimeout(function() {
            const alert = document.createElement('div');
            alert.className = 'fixed top-4 right-4 z-50 px-lg py-md bg-error text-on-error rounded-lg shadow-lg font-label-md';
            alert.textContent = '{{ session('error') }}';
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }, 200);
    @endif
});
</script>
@endSection
