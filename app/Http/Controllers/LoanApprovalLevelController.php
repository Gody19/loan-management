<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApprovalLevelRequest;
use App\Models\LoanApprovalLevel;
use Illuminate\Http\Request;

class LoanApprovalLevelController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', LoanApprovalLevel::class);

        $query = LoanApprovalLevel::with('organization');

        if (!$request->user()->hasRole('Super Administrator')) {
            $query->whereHas('organization', function ($q) use ($request) {
                $q->where('id', $request->user()->organizations()->pluck('organizations.id'));
            });
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $levels = $query->orderBy('organization_id')->orderBy('level')->paginate(15)->withQueryString();
        $organizations = \App\Models\Organization::active()->get();

        return view('loan-approval-levels.index', compact('levels', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', LoanApprovalLevel::class);
        $organizations = \App\Models\Organization::active()->get();

        return view('loan-approval-levels.create', compact('organizations'));
    }

    public function store(StoreApprovalLevelRequest $request)
    {
        $this->authorize('create', LoanApprovalLevel::class);

        LoanApprovalLevel::create($request->validated());

        return redirect()->route('loan-approval-levels.index')
            ->with('success', 'Approval level created.');
    }

    public function show(LoanApprovalLevel $loanApprovalLevel)
    {
        $this->authorize('view', $loanApprovalLevel);
        $loanApprovalLevel->load('organization');

        return view('loan-approval-levels.show', ['level' => $loanApprovalLevel]);
    }

    public function edit(LoanApprovalLevel $loanApprovalLevel)
    {
        $this->authorize('update', $loanApprovalLevel);
        $organizations = \App\Models\Organization::active()->get();

        return view('loan-approval-levels.edit', ['level' => $loanApprovalLevel, 'organizations' => $organizations]);
    }

    public function update(StoreApprovalLevelRequest $request, LoanApprovalLevel $loanApprovalLevel)
    {
        $this->authorize('update', $loanApprovalLevel);

        $loanApprovalLevel->update($request->validated());

        return redirect()->route('loan-approval-levels.show', $loanApprovalLevel)
            ->with('success', 'Approval level updated.');
    }

    public function destroy(LoanApprovalLevel $loanApprovalLevel)
    {
        $this->authorize('delete', $loanApprovalLevel);

        $loanApprovalLevel->delete();

        return redirect()->route('loan-approval-levels.index')
            ->with('success', 'Approval level deleted.');
    }
}
