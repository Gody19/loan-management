@extends('layouts.app')

@section('content')
<div class="max-w-2xl">
    <h1 class="font-headline-lg text-headline-lg text-on-surface mb-lg">Create Portfolio</h1>

    <form action="{{ route('portfolio.store') }}" method="POST" class="p-lg bg-white rounded-xl border border-outline-variant shadow-sm space-y-lg">
        @csrf

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Portfolio Name</label>
            <input type="text" name="name" placeholder="e.g., Retirement Fund" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required />
        </div>

        <div class="flex flex-col gap-xs">
            <label class="font-label-lg text-label-lg text-on-surface-variant">Description</label>
            <textarea name="description" placeholder="Brief description of your portfolio" rows="4" class="p-md border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary"></textarea>
        </div>

        <div class="flex gap-md">
            <button type="submit" class="flex-1 px-lg py-md bg-primary text-white rounded-lg hover:bg-primary-container transition-colors">Create Portfolio</button>
            <a href="{{ route('portfolio.index') }}" class="flex-1 px-lg py-md border border-outline-variant rounded-lg hover:bg-surface-container text-center transition-colors">Cancel</a>
        </div>
    </form>
</div>
@endsection
