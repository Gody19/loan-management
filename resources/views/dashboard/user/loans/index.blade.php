@extends('layouts.app')

@section('content')
<div class="space-y-lg">
    <div class="flex items-center justify-between">
        <h1 class="font-headline-lg text-headline-lg text-on-surface">My Loans</h1>
        <a href="{{ route('loans.create') }}" class="px-lg py-md bg-primary text-white rounded-lg hover:bg-primary-container transition-colors flex items-center gap-sm">
            <span class="material-symbols-outlined">add</span>
            Apply for Loan
        </a>
    </div>

    <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
        <p class="text-center text-on-surface-variant py-xl">Loan applications will appear here</p>
    </div>
</div>
@endsection
