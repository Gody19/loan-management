@extends('layouts.app')

@section('title', 'Edit Role - ' . config('app.name'))

@section('breadcrumb')
    @include('layouts.components.breadcrumb', ['breadcrumbs' => ['Dashboard' => route('dashboard'), 'Roles' => route('roles.index'), 'Edit' => '']])
@endsection

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Edit Role',
        'subtitle' => 'Update role: ' . $role->name,
    ])
@endsection

@section('content')

<form method="POST" action="{{ route('roles.update', $role) }}" data-validate>
    @csrf
    @method('PUT')

    <div class="card vicoba-card mb-4">
        <div class="card-header">
            <h6 class="mb-0 fw-semibold">Role Information</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Role Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                           id="name" name="name" value="{{ old('name', $role->name) }}">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card vicoba-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Permissions</h6>
            <div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">Select All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">Deselect All</button>
            </div>
        </div>
        <div class="card-body">
            @php $rolePermissions = $role->permissions->pluck('name')->toArray(); @endphp

            @foreach($permissions as $module => $modulePermissions)
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-uppercase fw-semibold mb-0" style="font-size: 0.8rem; color: #6c757d;">
                            {{ ucfirst($module) }}
                        </h6>
                        <button type="button" class="btn btn-sm btn-link p-0" onclick="toggleModule('{{ $module }}')">
                            Select All
                        </button>
                    </div>
                    <div class="row g-2">
                        @foreach($modulePermissions as $permission)
                            <div class="col-md-3 col-sm-4 col-6">
                                <div class="form-check">
                                    <input class="form-check-input permission-checkbox" type="checkbox"
                                           name="permissions[]" value="{{ $permission->name }}"
                                           id="perm_{{ $permission->id }}"
                                           data-module="{{ $module }}"
                                           {{ in_array($permission->name, old('permissions', $rolePermissions)) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="perm_{{ $permission->id }}" style="font-size: 0.85rem;">
                                        {{ explode('.', $permission->name)[1] }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @error('permissions')
                <div class="text-danger mt-2" style="font-size: 0.85rem;">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary vicoba-btn">
            <i class="bi bi-check-lg me-1"></i> Update Role
        </button>
        <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary vicoba-btn">
            Cancel
        </a>
    </div>
</form>

@endsection

@push('scripts')
<script>
    function selectAll() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
    }

    function deselectAll() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
    }

    function toggleModule(module) {
        document.querySelectorAll(`.permission-checkbox[data-module="${module}"]`).forEach(cb => {
            cb.checked = !cb.checked;
        });
    }
</script>
@endpush
