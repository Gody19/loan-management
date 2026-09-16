<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanPlanRequest;
use App\Models\LoanPlan;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\Request;

class LoanPlanController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LoanPlan::class);

        $query = LoanPlan::with('organization');

        if (! $request->user()->hasRole('Super Administrator')) {
            $query->whereHas('organization', function ($q) use ($request) {
                $q->where('id', $request->user()->organizations()->pluck('organizations.id'));
            });
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                    ->orWhere('code', 'LIKE', "%{$request->search}%");
            });
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('loan_purpose')) {
            $query->where('loan_purpose', $request->loan_purpose);
        }

        $loanPlans = $query->latest()->paginate(15)->withQueryString();
        $organizations = Organization::active()->get();

        return view('loan-plans.index', compact('loanPlans', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', LoanPlan::class);
        $organizations = Organization::active()->get();

        return view('loan-plans.create', compact('organizations'));
    }

    public function store(StoreLoanPlanRequest $request)
    {
        $loanPlan = LoanPlan::create($request->validated());
        $this->audit->log('loan_plan.created', $loanPlan, [], $loanPlan->toArray());

        return redirect()->route('loan-plans.index')->with('success', 'Loan plan "'.$loanPlan->name.'" created.');
    }

    public function show(LoanPlan $loanPlan)
    {
        $this->authorize('view', $loanPlan);
        $loanPlan->load(['organization', 'creator']);

        return view('loan-plans.show', ['loanPlan' => $loanPlan]);
    }

    public function edit(LoanPlan $loanPlan)
    {
        $this->authorize('update', $loanPlan);
        $organizations = Organization::active()->get();

        return view('loan-plans.edit', ['loanPlan' => $loanPlan, 'organizations' => $organizations]);
    }

    public function update(StoreLoanPlanRequest $request, LoanPlan $loanPlan)
    {
        $this->authorize('update', $loanPlan);
        $old = $loanPlan->only(array_keys($request->validated()));
        $loanPlan->update($request->validated());
        $this->audit->log('loan_plan.updated', $loanPlan, $old, $loanPlan->toArray());

        return redirect()->route('loan-plans.index')->with('success', 'Loan plan updated.');
    }

    public function destroy(LoanPlan $loanPlan)
    {
        $this->authorize('delete', $loanPlan);
        $loanPlan->delete();
        $this->audit->log('loan_plan.deleted', $loanPlan);

        return redirect()->route('loan-plans.index')->with('success', 'Loan plan deleted.');
    }

    public function activate(LoanPlan $loanPlan)
    {
        $this->authorize('update', $loanPlan);
        $old = ['status' => $loanPlan->status->value];
        $loanPlan->update(['status' => 'active']);
        $this->audit->log('loan_plan.activated', $loanPlan, $old, ['status' => 'active']);

        return redirect()->route('loan-plans.show', $loanPlan)->with('success', 'Loan plan activated.');
    }

    public function deactivate(LoanPlan $loanPlan)
    {
        $this->authorize('update', $loanPlan);
        $old = ['status' => $loanPlan->status->value];
        $loanPlan->update(['status' => 'inactive']);
        $this->audit->log('loan_plan.deactivated', $loanPlan, $old, ['status' => 'inactive']);

        return redirect()->route('loan-plans.show', $loanPlan)->with('success', 'Loan plan deactivated.');
    }
}
