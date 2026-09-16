@extends('layouts.app')

@section('title', 'Manage Users - ' . $branch->name)

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Manage Users',
        'subtitle' => 'Assign users to ' . $branch->name,
        'breadcrumb' => [
            ['label' => 'Branches', 'url' => route('branches.index')],
            ['label' => $branch->name, 'url' => route('branches.show', $branch)],
            ['label' => 'Users'],
        ],
    ])
@endsection

@section('content')

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card vicoba-card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Add User</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('branches.assign-user', $branch) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Select User <span class="text-danger">*</span></label>
                        <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id">
                            <option value="">Choose a user...</option>
                            @foreach($users as $user)
                                @if(!$branch->users->contains('id', $user->id))
                                    <option value="{{ $user->id }}">{{ $user->fullname }} ({{ $user->email }})</option>
                                @endif
                            @endforeach
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary vicoba-btn w-100">
                        <i class="bi bi-plus-lg me-1"></i> Assign User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card vicoba-card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Assigned Users ({{ $branch->users->count() }})</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table vicoba-table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($branch->users as $user)
                                <tr>
                                    <td>{{ $user->fullname }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('branches.remove-user', $branch) }}" method="POST" class="d-inline"
                                              data-confirm="Remove this user from the branch?">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-lg"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No users assigned.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
