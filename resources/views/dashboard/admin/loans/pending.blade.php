@extends('layouts.app')
@section('title', 'Pending Loan Approvals')
@section('content')
    <div class="space-y-lg">
        <div class="mb-2xl">
            <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">Pending Loan Approvals</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">Review and process loan applications</p>
        </div>

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Applicant</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-right">Amount</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Purpose</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Tenure</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Monthly Income</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Applied</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-center">Actions</th>
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
                                <td class="px-xl py-lg text-on-surface-variant">{{ $loan->monthly_income ? \App\Helpers\Currency::tzs($loan->monthly_income) : '-' }}</td>
                                <td class="px-xl py-lg text-on-surface-variant text-body-sm">{{ $loan->created_at->diffForHumans() }}</td>
                                <td class="px-xl py-lg text-center">
                                    <div class="flex items-center justify-center gap-sm">
                                        <form action="{{ route('loans.approve', $loan) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-md py-sm bg-tertiary text-on-tertiary rounded-lg font-label-md hover:opacity-90 transition-all flex items-center gap-xs">
                                                <span class="material-symbols-outlined text-sm">check_circle</span> Approve
                                            </button>
                                        </form>
                                        <form action="{{ route('loans.reject', $loan) }}" method="POST" class="inline" onsubmit="return confirm('Reject this loan application?')">
                                            @csrf
                                            <button type="submit" class="px-md py-sm bg-error-container text-on-error-container rounded-lg font-label-md hover:opacity-90 transition-all flex items-center gap-xs">
                                                <span class="material-symbols-outlined text-sm">cancel</span> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-xl py-lg text-center text-on-surface-variant">No pending loan applications.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-xl py-md bg-surface-container-low border-t border-outline-variant">
                {{ $loans->links() }}
            </div>
        </div>
    </div>
@endsection
