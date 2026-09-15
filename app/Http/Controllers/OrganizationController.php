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

        return view('organizations.create');
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        $organization = $this->organizationService->create($request->validated());

        app(AuditService::class)->log('organization.created', $organization, [], $organization->toArray());

        return redirect()->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function show(Organization $organization): View
    {
        $this->authorize('view', $organization);

        $organization->load('branches', 'users');

        return view('organizations.show', compact('organization'));
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

        app(AuditService::class)->log('organization.updated', $organization);

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
}
