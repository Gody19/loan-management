@extends('layouts.app')

@section('content')
<div class="max-w-2xl">
    <h1 class="font-headline-lg text-headline-lg text-on-surface mb-lg">Create Budget</h1>

    <form action="{{ route('budget.store') }}" method="POST" class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm space-y-lg">
        @csrf

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Budget Name</label>
            <input type="text" name="name" placeholder="e.g., Monthly Groceries" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
        </div>

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Limit Amount</label>
            <input type="number" name="limit_amount" step="0.01" placeholder="10000" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
        </div>

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Period</label>
            <select name="period" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="yearly">Yearly</option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-lg">
            <div class="flex flex-col gap-xs">
                <label class="font-label-lg text-label-lg text-on-surface-variant">Start Date</label>
                <input type="date" name="start_date" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
            </div>
            <div class="flex flex-col gap-xs">
                <label class="font-label-lg text-label-lg text-on-surface-variant">End Date</label>
                <input type="date" name="end_date" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
            </div>
        </div>

        <div class="flex gap-md">
            <button type="submit" class="flex-1 px-lg py-md bg-primary text-white rounded-lg hover:bg-primary-container transition-colors">Create Budget</button>
            <a href="{{ route('budget.index') }}" class="flex-1 px-lg py-md border border-outline-variant rounded-lg hover:bg-surface-container text-center transition-colors">Cancel</a>
        </div>
    </form>
</div>
@endsection
