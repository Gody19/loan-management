@extends('layouts.app')

@section('title', 'Roles - ' . config('app.name'))

@section('breadcrumb')
    @include('layouts.components.breadcrumb', ['breadcrumbs' => ['Dashboard' => route('dashboard'), 'Roles' => '']])
@endsection

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Roles',
        'subtitle' => 'Manage system roles and their permissions',
        'actions' => '<a href="' . route('roles.create') . '" class="btn btn-primary vicoba-btn"><i class="bi bi-plus-lg me-1"></i> Create Role</a>',
    ])
@endsection

@section('content')

<div class="card vicoba-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table vicoba-table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Permissions</th>
                        <th>Users</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $role->name }}</div>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $role->permissions_count ?? $role->permissions->count() }} permissions</span>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info">{{ $role->users_count ?? 0 }} users</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($role->name !== 'Super Administrator')
                                        <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Are you sure you want to delete this role?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No roles found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
