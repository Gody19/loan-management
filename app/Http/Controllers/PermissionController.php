<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Display permissions matrix.
     */
    public function index(): View
    {
        $this->authorize('permission.view');

        $permissions = $this->permissionService->getGroupedPermissions();
        $roles = Role::with('permissions')->get();

        return view('permissions.index', compact('permissions', 'roles'));
    }

    /**
     * Update permissions for a role.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('permission.assign');

        $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();

        $role->syncPermissions($request->permissions ?? []);

        app(AuditService::class)->log(
            'role.permissions.updated',
            $role,
            ['permissions' => $oldPermissions],
            ['permissions' => $request->permissions ?? []]
        );

        return redirect()->route('permissions.index')
            ->with('success', 'Permissions updated for role: '.$role->name);
    }
}
