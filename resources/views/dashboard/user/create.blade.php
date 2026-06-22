@extends('layouts.app')

@section('content')
<div class="max-w-2xl">
    <h1 class="font-headline-lg text-headline-lg text-on-surface mb-lg">Apply for Loan</h1>

    <form action="{{ route('loans.store') }}" method="POST" class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm space-y-lg">
        @csrf

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Loan Amount</label>
            <input type="number" name="amount" step="0.01" placeholder="" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
        </div>

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Purpose</label>
            <input type="text" name="purpose" placeholder="e.g., Home renovation" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
        </div>

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Monthly Income</label>
            <input type="number" name="monthly_income" step="0.01" placeholder="50000" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" />
        </div>

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Tenure (Months)</label>
            <input type="number" name="tenure_months" placeholder="24" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
        </div>

        <div class="flex gap-md">
            <button type="submit" class="flex-1 px-lg py-md bg-primary text-white rounded-lg hover:bg-primary-container transition-colors">Submit Application</button>
            <a href="{{ route('loans.index') }}" class="flex-1 px-lg py-md border border-outline-variant rounded-lg hover:bg-surface-container text-center transition-colors">Cancel</a>
        </div>
    </form>
</div>
@endsection
