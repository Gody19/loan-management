<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVicobaGroupRequest;
use App\Models\Branch;
use App\Models\VicobaGroup;
use App\Services\AuditService;
use App\Services\OrganizationContext;
use App\Services\VicobaGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VicobaGroupController extends Controller
{
    public function __construct(
        protected VicobaGroupService $groupService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VicobaGroup::class);

        $groups = $this->groupService->getFilteredQuery(
            $request->only(['search', 'status', 'branch_id']),
            $request->user(),
        )->latest()->paginate(15)->withQueryString();

        $branches = OrganizationContext::scopedOrganizations($request->user())
            ->get()
            ->flatMap(fn ($org) => $org->branches()->active()->get());

        return view('vicoba-groups.index', compact('groups', 'branches'));
    }

    public function create(): View
    {
        $this->authorize('create', VicobaGroup::class);

        $branches = OrganizationContext::scopedOrganizations()
            ->get()
            ->flatMap(fn ($org) => $org->branches()->active()->get());

        return view('vicoba-groups.create', compact('branches'));
    }

    public function store(StoreVicobaGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', VicobaGroup::class);

        $group = $this->groupService->create($request->validated());

        app(AuditService::class)->log('group.created', $group, [], $group->toArray());

        return redirect()->route('vicoba-groups.index')
            ->with('success', 'VICOBA Group created successfully.');
    }

    public function show(VicobaGroup $vicoba_group): View
    {
        $this->authorize('view', $vicoba_group);

        $vicoba_group->load('branch.organization');

        return view('vicoba-groups.show', ['group' => $vicoba_group]);
    }

    public function edit(VicobaGroup $vicoba_group): View
    {
        $this->authorize('update', $vicoba_group);

        $branches = OrganizationContext::scopedOrganizations()
            ->get()
            ->flatMap(fn ($org) => $org->branches()->active()->get());

        return view('vicoba-groups.edit', ['group' => $vicoba_group, 'branches' => $branches]);
    }

    public function update(StoreVicobaGroupRequest $request, VicobaGroup $vicoba_group): RedirectResponse
    {
        $this->authorize('update', $vicoba_group);

        $this->groupService->update($vicoba_group, $request->validated());

        app(AuditService::class)->log('group.updated', $vicoba_group);

        return redirect()->route('vicoba-groups.index')
            ->with('success', 'VICOBA Group updated successfully.');
    }

    public function destroy(VicobaGroup $vicoba_group): RedirectResponse
    {
        $this->authorize('delete', $vicoba_group);

        $name = $vicoba_group->name;
        $vicoba_group->delete();

        return redirect()->route('vicoba-groups.index')
            ->with('success', "VICOBA Group \"{$name}\" has been deleted.");
    }
}
