@extends('layouts.app')

@section('title', 'Permissions - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Permissions Matrix',
        'subtitle' => 'Manage permissions for each role',
        'breadcrumb' => [
            ['label' => 'Permissions'],
        ],
    ])
@endsection

@section('content')

<div class="card vicoba-card">
    <div class="card-body">
        <form method="POST" action="{{ route('permissions.update', ['role' => '__ROLE_ID__']) }}" id="permissionForm">
            @csrf

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th style="min-width: 150px;">Permission</th>
                            @foreach($roles as $role)
                                <th class="text-center" style="min-width: 100px;">
                                    <div style="font-size: 0.8rem;">{{ $role->name }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($permissions as $module => $modulePermissions)
                            <tr class="table-light">
                                <td colspan="{{ count($roles) + 1 }}" class="fw-semibold text-uppercase" style="font-size: 0.8rem;">
                                    {{ ucfirst($module) }}
                                </td>
                            </tr>
                            @foreach($modulePermissions as $permission)
                                <tr>
                                    <td style="font-size: 0.85rem;">
                                        {{ $permission->name }}
                                    </td>
                                    @foreach($roles as $role)
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input role-perm-checkbox"
                                                       type="checkbox"
                                                       name="roles[{{ $role->id }}][]"
                                                       value="{{ $permission->name }}"
                                                       data-role="{{ $role->id }}"
                                                       {{ $role->permissions->contains($permission) ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" class="btn btn-primary vicoba-btn" onclick="savePermissions()">
            <i class="bi bi-check-lg me-1"></i> Save Permissions
        </button>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function savePermissions() {
        // For each role, collect checked permissions and submit
        const roles = @json($roles->pluck('id', 'name'));

        for (const [roleId, roleName] of Object.entries(roles)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ url("permissions") }}/' + roleId;

            // CSRF token
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            // Method spoofing
            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'POST';
            form.appendChild(method);

            // Get checked permissions for this role
            const checkboxes = document.querySelectorAll(`.role-perm-checkbox[data-role="${roleId}"]:checked`);
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'permissions[]';
                input.value = cb.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            break; // Submit one at a time
        }
    }
</script>
@endpush
