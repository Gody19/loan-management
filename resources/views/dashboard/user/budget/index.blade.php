@extends('layouts.app')

@section('content')
<div class="space-y-lg">
    <div class="flex items-center justify-between">
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Budget</h1>
        <a href="{{ route('budget.create') }}" class="px-lg py-md bg-primary text-white rounded-lg hover:bg-primary-container transition-colors flex items-center gap-sm">
            <span class="material-symbols-outlined">add</span>
            Create Budget
        </a>
    </div>

    <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
        <p class="text-center text-on-surface-variant py-xl">Budget data will appear here</p>
    </div>
</div>
@endsection
