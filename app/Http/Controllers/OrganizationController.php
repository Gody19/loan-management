<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(
        protected OrganizationService $organizationService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Organization::class);

        $query = $this->organizationService->getForUser(auth()->user());
        $query = $this->organizationService->applyFilters($query, $request->only(['search', 'status']));

        $organizations = $query->latest()->paginate(15)->withQueryString();

        return view('organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        $this->authorize('create', Organization::class);

        $users = User::active()->get();

        return view('organizations.create', compact('users'));
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        $adminType = $request->input('admin_type');

        $organization = $this->organizationService->createWithAdmin(
            $request->only(['name', 'registration_number', 'phone', 'email', 'address', 'region', 'district', 'status']),
            $adminType === 'create' ? $request->only(['admin_name', 'admin_email', 'admin_phone', 'admin_password']) : null,
            $adminType === 'assign' ? $request->input('admin_user_id') : null,
        );

        app(AuditService::class)->log(
            'organization_created',
            $organization,
            [],
            $organization->toArray()
        );

        if ($adminType === 'create') {
            app(AuditService::class)->log(
                'organization_admin_created',
                $organization,
                ['user_email' => $request->input('admin_email')],
                []
            );
        } elseif ($adminType === 'assign') {
            app(AuditService::class)->log(
                'organization_admin_assigned',
                $organization,
                ['user_id' => $request->input('admin_user_id')],
                []
            );
        }

        return redirect()->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function show(Organization $organization): View
    {
        $this->authorize('view', $organization);

        $organization->load([
            'branches',
            'members' => fn ($q) => $q->limit(10),
        ]);

        // Load VICOBA groups scoped to this organization's branches only
        $branchIds = $organization->branches->pluck('id')->toArray();
        $organization->setRelation(
            'vicobaGroups',
            empty($branchIds)
                ? collect()
                : \App\Models\VicobaGroup::whereIn('branch_id', $branchIds)->get()
        );

        $administrators = $this->organizationService->getAdministrators($organization)->get();
        $users = User::active()->get();

        return view('organizations.show', compact('organization', 'administrators', 'users'));
    }

    public function edit(Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('organizations.edit', compact('organization'));
    }

    public function update(StoreOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $this->organizationService->update($organization, $request->validated());

        app(AuditService::class)->log('organization_updated', $organization);

        return redirect()->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $name = $organization->name;
        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('success', "Organization \"{$name}\" has been deleted.");
    }

    public function users(Organization $organization): View
    {
        $this->authorize('assignUser', $organization);

        $organization->load('users');
        $users = User::latest()->get();

        return view('organizations.users', compact('organization', 'users'));
    }

    public function assignUser(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('assignUser', $organization);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $organization->users()->syncWithoutDetaching($request->user_id);

        return back()->with('success', 'User assigned to organization successfully.');
    }

    public function removeUser(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('assignUser', $organization);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $organization->users()->detach($request->user_id);

        return back()->with('success', 'User removed from organization.');
    }

    /**
     * Assign an existing user as Organization Administrator.
     */
    public function assignAdmin(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $request->validate([
            'admin_user_id' => ['required', 'exists:users,id'],
        ]);

        $this->organizationService->assignAdmin($organization, $request->input('admin_user_id'));

        app(AuditService::class)->log(
            'organization_admin_assigned',
            $organization,
            ['user_id' => $request->input('admin_user_id')],
            []
        );

        return back()->with('success', 'Administrator assigned successfully.');
    }

    /**
     * Remove an administrator from an organization.
     */
    public function removeAdmin(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('update', $organization);

        try {
            $this->organizationService->removeAdmin($organization, $user->id);

            app(AuditService::class)->log(
                'organization_admin_removed',
                $organization,
                ['user_id' => $user->id],
                []
            );

            return back()->with('success', 'Administrator removed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
