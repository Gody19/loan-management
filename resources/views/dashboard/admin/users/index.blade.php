@extends('layouts.app')
@section('title', 'User Management')
@section('content')
    <div class="space-y-lg">
        <div class="mb-2xl">
            <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">User Management</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">Manage all system users</p>
        </div>

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Name</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Email</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Role</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Status</th>
                            <th class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @forelse($users as $user)
                            <tr class="hover:bg-surface-container transition-colors">
                                <td class="px-xl py-lg">
                                    <div>
                                        <p class="font-body-md text-on-surface">{{ $user->fullname }}</p>
                                        <p class="text-body-sm text-on-surface-variant">{{ '@' . $user->username }}</p>
                                    </div>
                                </td>
                                <td class="px-xl py-lg text-on-surface-variant">{{ $user->email }}</td>
                                <td class="px-xl py-lg">
                                    <span class="px-sm py-xs bg-primary/10 text-primary font-label-md text-[10px] rounded uppercase tracking-widest font-bold">{{ ucfirst($user->role) }}</span>
                                </td>
                                <td class="px-xl py-lg">
                                    @php
                                        $statusClass = match($user->is_active) {
                                            'active' => 'bg-tertiary-fixed-dim/20 text-tertiary',
                                            'pending' => 'bg-surface-container-high text-on-surface-variant',
                                            'inactive' => 'bg-error-container text-error',
                                            default => 'bg-surface-container-high text-on-surface-variant',
                                        };
                                    @endphp
                                    <span class="px-sm py-xs {{ $statusClass }} font-label-md text-[10px] rounded uppercase tracking-widest font-bold">{{ ucfirst($user->is_active) }}</span>
                                </td>
                                <td class="px-xl py-lg text-on-surface-variant text-body-sm">{{ $user->created_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-xl py-lg text-center text-on-surface-variant">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-xl py-md bg-surface-container-low border-t border-outline-variant">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
