<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(
        protected BranchService $branchService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $branches = $this->branchService->getFilteredQuery(
            $request->only(['search', 'status', 'organization_id'])
        )->latest()->paginate(15)->withQueryString();

        $organizations = Organization::active()->get();

        return view('branches.index', compact('branches', 'organizations'));
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        $organizations = Organization::active()->get();

        return view('branches.create', compact('organizations'));
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $branch = $this->branchService->create($request->validated());

        app(AuditService::class)->log('branch.created', $branch, [], $branch->toArray());

        return redirect()->route('branches.index')
            ->with('success', 'Branch created successfully.');
    }

    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);

        $branch->load('organization', 'groups', 'users');

        return view('branches.show', compact('branch'));
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        $organizations = Organization::active()->get();

        return view('branches.edit', compact('branch', 'organizations'));
    }

    public function update(StoreBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $this->branchService->update($branch, $request->validated());

        app(AuditService::class)->log('branch.updated', $branch);

        return redirect()->route('branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        $name = $branch->name;
        $branch->delete();

        return redirect()->route('branches.index')
            ->with('success', "Branch \"{$name}\" has been deleted.");
    }

    public function users(Branch $branch): View
    {
        $this->authorize('assignUser', $branch);

        $branch->load('users');
        $users = User::latest()->get();

        return view('branches.users', compact('branch', 'users'));
    }

    public function assignUser(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('assignUser', $branch);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $branch->users()->syncWithoutDetaching($request->user_id);

        return back()->with('success', 'User assigned to branch successfully.');
    }

    public function removeUser(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('assignUser', $branch);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $branch->users()->detach($request->user_id);

        return back()->with('success', 'User removed from branch.');
    }
}
