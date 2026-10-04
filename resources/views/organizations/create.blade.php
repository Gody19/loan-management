@extends('layouts.app')

@section('title', 'Create Organization - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Create Organization',
        'subtitle' => 'Add a new organization with initial administrator',
        'breadcrumb' => [
            ['label' => 'Organizations', 'url' => route('organizations.index')],
            ['label' => 'Create'],
        ],
    ])
@endsection

@section('content')

<form method="POST" action="{{ route('organizations.store') }}" data-validate>
            @csrf

            {{-- Organization Information --}}
            <div class="card vicoba-card mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Organization Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Organization Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="registration_number" class="form-label">Registration Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('registration_number') is-invalid @enderror"
                                   id="registration_number" name="registration_number" value="{{ old('registration_number') }}">
                            @error('registration_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                   id="phone" name="phone" value="{{ old('phone') }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control @error('address') is-invalid @enderror"
                                      id="address" name="address" rows="2">{{ old('address') }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="region" class="form-label">Region</label>
                            <input type="text" class="form-control @error('region') is-invalid @enderror"
                                   id="region" name="region" value="{{ old('region') }}">
                            @error('region')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="district" class="form-label">District</label>
                            <input type="text" class="form-control @error('district') is-invalid @enderror"
                                   id="district" name="district" value="{{ old('district') }}">
                            @error('district')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                @foreach(\App\Enums\OrganizationStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ old('status', 'active') === $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Initial Administrator --}}
            <div class="card vicoba-card mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Initial Organization Administrator</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="admin_type" id="admin_create" value="create" {{ old('admin_type', 'create') === 'create' ? 'checked' : '' }}>
                            <label class="form-check-label" for="admin_create">Create New User</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="admin_type" id="admin_assign" value="assign" {{ old('admin_type') === 'assign' ? 'checked' : '' }}>
                            <label class="form-check-label" for="admin_assign">Assign Existing User</label>
                        </div>
                        @error('admin_type')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Create New User Form --}}
                    <div id="create-admin-form" class="{{ old('admin_type', 'create') !== 'create' ? 'd-none' : '' }}">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="admin_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('admin_name') is-invalid @enderror"
                                       id="admin_name" name="admin_name" value="{{ old('admin_name') }}">
                                @error('admin_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('admin_email') is-invalid @enderror"
                                       id="admin_email" name="admin_email" value="{{ old('admin_email') }}">
                                @error('admin_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_phone" class="form-label">Phone</label>
                                <input type="text" class="form-control @error('admin_phone') is-invalid @enderror"
                                       id="admin_phone" name="admin_phone" value="{{ old('admin_phone') }}">
                                @error('admin_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_password" class="form-label">Temporary Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control @error('admin_password') is-invalid @enderror"
                                       id="admin_password" name="admin_password">
                                @error('admin_password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="admin_password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control"
                                       id="admin_password_confirmation" name="admin_password_confirmation">
                            </div>
                        </div>
                    </div>

                    {{-- Assign Existing User Form --}}
                    <div id="assign-admin-form" class="{{ old('admin_type') !== 'assign' ? 'd-none' : '' }}">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="admin_user_id" class="form-label">Select User <span class="text-danger">*</span></label>
                                <select class="form-select @error('admin_user_id') is-invalid @enderror"
                                        id="admin_user_id" name="admin_user_id">
                                    <option value="">Select a user...</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('admin_user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->fullname }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('admin_user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary vicoba-btn">
                    <i class="bi bi-check-lg me-1"></i> Create Organization
                </button>
                <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary vicoba-btn">
                    Cancel
                </a>
            </div>
        </form>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const createRadio = document.getElementById('admin_create');
        const assignRadio = document.getElementById('admin_assign');
        const createForm = document.getElementById('create-admin-form');
        const assignForm = document.getElementById('assign-admin-form');

        function toggleAdminForms() {
            if (createRadio.checked) {
                createForm.classList.remove('d-none');
                assignForm.classList.add('d-none');
            } else {
                createForm.classList.add('d-none');
                assignForm.classList.remove('d-none');
            }
        }

        createRadio.addEventListener('change', toggleAdminForms);
        assignRadio.addEventListener('change', toggleAdminForms);
    });
</script>
@endpush
