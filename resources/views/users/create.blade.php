@extends('layouts.app')

@section('title', 'Create User - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Create User',
        'subtitle' => 'Add a new system user',
        'breadcrumb' => [
            ['label' => 'Users', 'url' => route('users.index')],
            ['label' => 'Create'],
        ],
    ])
@endsection

@section('content')

        <form method="POST" action="{{ route('users.store') }}" data-validate>
            @csrf

            <div class="card vicoba-card mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="fullname" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('fullname') is-invalid @enderror"
                                   id="fullname" name="fullname" value="{{ old('fullname') }}">
                            @error('fullname')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('username') is-invalid @enderror"
                                   id="username" name="username" value="{{ old('username') }}">
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                   id="phone" name="phone" value="{{ old('phone') }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="nida_number" class="form-label">NIDA Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('nida_number') is-invalid @enderror"
                                   id="nida_number" name="nida_number" value="{{ old('nida_number') }}">
                            @error('nida_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                                   id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                            @error('date_of_birth')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                @foreach(\App\Enums\Gender::cases() as $gender)
                                    <option value="{{ $gender->value }}" {{ old('gender') === $gender->value ? 'selected' : '' }}>
                                        {{ $gender->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card vicoba-card mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Account Credentials</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control"
                                   id="password_confirmation" name="password_confirmation">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card vicoba-card mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Roles & Permissions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach($roles as $role)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="roles[]" value="{{ $role->name }}"
                                           id="role_{{ $role->id }}"
                                           {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="role_{{ $role->id }}">
                                        {{ $role->name }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('roles')
                        <div class="text-danger mt-2" style="font-size: 0.85rem;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary vicoba-btn">
                    <i class="bi bi-check-lg me-1"></i> Create User
                </button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary vicoba-btn">
                    Cancel
                </a>
            </div>
        </form>

@endsection
