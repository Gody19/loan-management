@extends('layouts.app')
@section('title', 'Profile')
@section('content')
<div class="max-w-4xl mx-auto space-y-xl">
    @if(session('success'))
        <div class="p-md bg-tertiary-container/20 text-tertiary rounded-lg border border-tertiary-container/30">{{ session('success') }}</div>
    @endif

    <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-outline-variant">
        <div class="flex items-center justify-between mb-lg">
            <div class="flex items-center gap-md">
                <div class="w-14 h-14 rounded-full bg-primary flex items-center justify-center text-on-primary font-bold text-xl">
                    {{ strtoupper(substr($user->fullname, 0, 1)) }}
                </div>
                <div>
                    <h4 class="font-headline-sm text-headline-sm text-on-surface">{{ $user->fullname }}</h4>
                    <p class="text-body-sm text-on-surface-variant">{{ '@' . $user->username }} &middot; {{ ucfirst($user->role) }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route('profile.update') }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Full Name</label>
                    <input name="fullname" value="{{ old('fullname', $user->fullname) }}" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" />
                    @error('fullname')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Username</label>
                    <input name="username" value="{{ old('username', $user->username) }}" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" />
                    @error('username')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Email</label>
                    <input name="email" type="email" value="{{ old('email', $user->email) }}" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" />
                    @error('email')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Phone</label>
                    <input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" />
                    @error('phone')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Date of Birth</label>
                    <input name="date_of_birth" type="date" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" />
                    @error('date_of_birth')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Gender</label>
                    <select name="gender" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all">
                        <option value="">Select Gender</option>
                        <option value="male" @selected(old('gender', $user->gender) === 'male')>Male</option>
                        <option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option>
                        <option value="other" @selected(old('gender', $user->gender) === 'other')>Other</option>
                    </select>
                    @error('gender')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">NIDA Number</label>
                    <input value="{{ $user->nida_number }}" class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg text-on-surface-variant font-body-md cursor-not-allowed" readonly />
                    <p class="text-body-xs text-on-surface-variant">Cannot be changed</p>
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Account Status</label>
                    <input value="{{ ucfirst($user->is_active) }}" class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg text-on-surface-variant font-body-md cursor-not-allowed" readonly />
                </div>
            </div>

            <div class="mt-xl pt-lg border-t border-outline-variant flex justify-end">
                <button type="submit" class="px-xl py-sm bg-primary text-on-primary rounded-lg font-label-lg hover:opacity-90 transition-all flex items-center gap-sm">
                    <span class="material-symbols-outlined text-sm">save</span> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
