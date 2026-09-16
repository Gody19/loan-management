@extends('layouts.app')

@section('title', $user->fullname . ' - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $user->fullname,
        'subtitle' => 'User Details',
        'breadcrumb' => [
            ['label' => 'Users', 'url' => route('users.index')],
            ['label' => $user->fullname],
        ],
        'actions' => '<a href="' . route('users.edit', $user) . '" class="btn btn-primary vicoba-btn"><i class="bi bi-pencil me-1"></i> Edit</a>',
    ])
@endsection

@section('content')

<div class="row g-4">

    {{-- User Profile Card --}}
    <div class="col-lg-4">
        <div class="card vicoba-card">
            <div class="card-body text-center">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <span class="text-white fw-bold" style="font-size: 2rem;">{{ $user->initials }}</span>
                </div>
                <h5 class="fw-semibold mb-1">{{ $user->fullname }}</h5>
                <p class="text-muted mb-3" style="font-size: 0.85rem;">@{{ $user->username }}</p>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge bg-{{ $user->status->color() }} badge-status">
                        {{ $user->status->label() }}
                    </span>
                    @foreach($user->roles as $role)
                        <span class="badge bg-primary-subtle text-primary">{{ $role->name }}</span>
                    @endforeach
                </div>

                <hr>

                <div class="text-start" style="font-size: 0.85rem;">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><i class="bi bi-envelope me-2"></i>Email</span>
                        <span>{{ $user->email }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><i class="bi bi-phone me-2"></i>Phone</span>
                        <span>{{ $user->phone ?? '-' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><i class="bi bi-person me-2"></i>NIDA</span>
                        <span>{{ $user->nida_number }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><i class="bi bi-calendar me-2"></i>DOB</span>
                        <span>{{ $user->date_of_birth ? $user->date_of_birth->format('M d, Y') : '-' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><i class="bi bi-gender-ambiguous me-2"></i>Gender</span>
                        <span>{{ $user->gender ? ucfirst($user->gender) : '-' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><i class="bi bi-clock me-2"></i>Last Login</span>
                        <span>{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-plus me-2"></i>Created</span>
                        <span>{{ $user->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Permissions --}}
    <div class="col-lg-8">
        <div class="card vicoba-card mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Assigned Roles</h6>
            </div>
            <div class="card-body">
                @if($user->roles->isEmpty())
                    <p class="text-muted mb-0">No roles assigned.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Permissions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($user->roles as $role)
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary">{{ $role->name }}</span>
                                        </td>
                                        <td>
                                            @foreach($role->permissions as $permission)
                                                <span class="badge bg-light text-dark">{{ $permission->name }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="card vicoba-card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Direct Permissions</h6>
            </div>
            <div class="card-body">
                @if($user->permissions->isEmpty())
                    <p class="text-muted mb-0">No direct permissions assigned.</p>
                @else
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($user->permissions as $permission)
                            <span class="badge bg-success-subtle text-success">{{ $permission->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection
