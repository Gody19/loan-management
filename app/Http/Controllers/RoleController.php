<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Services\AuditService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Display a listing of roles.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::withCount('users')->get();

        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): View
    {
        $this->authorize('create', Role::class);

        $permissions = $this->permissionService->getGroupedPermissions();

        return view('roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        app(AuditService::class)->log('role.created', $role, [], $role->toArray());

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        $permissions = $this->permissionService->getGroupedPermissions();
        $role->load('permissions');

        return view('roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified role.
     */
    public function update(StoreRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $oldPermissions = $role->permissions->pluck('name')->toArray();

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        app(AuditService::class)->log(
            'role.updated',
            $role,
            ['permissions' => $oldPermissions],
            ['permissions' => $request->permissions]
        );

        return redirect()->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->name === 'Super Administrator') {
            return back()->with('error', 'Cannot delete the Super Administrator role.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
