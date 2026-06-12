@extends('layouts.app')

@section('content')
<div class="space-y-lg">
    <div class="flex items-center justify-between">
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Portfolio</h1>
        <a href="{{ route('portfolio.create') }}" class="px-lg py-md bg-primary text-white rounded-lg hover:bg-primary-container transition-colors flex items-center gap-sm">
            <span class="material-symbols-outlined">add</span>
            Add Portfolio
        </a>
    </div>

    <div class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm">
        <p class="text-center text-on-surface-variant py-xl">Portfolio data will appear here</p>
    </div>
</div>
@endsection
