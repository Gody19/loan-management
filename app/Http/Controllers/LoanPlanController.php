<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanPlanRequest;
use App\Models\LoanPlan;
use App\Models\LoanPlanCollateralRule;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanPlanController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LoanPlan::class);

        $query = LoanPlan::with('organization');

        OrganizationContext::scopeToUserOrganizations($query, $request->user());

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
        $organizations = OrganizationContext::scopedOrganizations($request->user())->active()->get();

        return view('loan-plans.index', compact('loanPlans', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', LoanPlan::class);
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();

        return view('loan-plans.create', compact('organizations'));
    }

    public function store(StoreLoanPlanRequest $request)
    {
        $this->authorize('create', LoanPlan::class);

        OrganizationContext::authorizeOrganization((int) $request->organization_id);

        $loanPlan = LoanPlan::create($request->validated());

        $this->syncCollateralRules($loanPlan, $request);

        $this->audit->log('loan_plan.created', $loanPlan, [], $loanPlan->toArray());

        return redirect()->route('loan-plans.index')->with('success', 'Loan plan "'.$loanPlan->name.'" created.');
    }

    public function show(LoanPlan $loanPlan)
    {
        $this->authorize('view', $loanPlan);
        $loanPlan->load(['organization', 'creator', 'collateralRules']);

        return view('loan-plans.show', ['loanPlan' => $loanPlan]);
    }

    public function edit(LoanPlan $loanPlan)
    {
        $this->authorize('update', $loanPlan);
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();
        $loanPlan->load('collateralRules');

        return view('loan-plans.edit', ['loanPlan' => $loanPlan, 'organizations' => $organizations]);
    }

    public function update(StoreLoanPlanRequest $request, LoanPlan $loanPlan)
    {
        $this->authorize('update', $loanPlan);
        $old = $loanPlan->only(array_keys($request->validated()));
        $loanPlan->update($request->validated());

        $this->syncCollateralRules($loanPlan, $request);

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

    private function syncCollateralRules(LoanPlan $loanPlan, Request $request): void
    {
        $rules = $request->input('collateral_rules', []);

        $existingIds = collect($rules)->pluck('id')->filter()->toArray();
        $loanPlan->collateralRules()->whereNotIn('id', $existingIds)->delete();

        foreach ($rules as $ruleData) {
            if (empty($ruleData['minimum_amount']) || empty($ruleData['maximum_amount'])) {
                continue;
            }

            $data = [
                'minimum_amount' => $ruleData['minimum_amount'],
                'maximum_amount' => $ruleData['maximum_amount'],
                'collateral_required' => !empty($ruleData['collateral_required']),
                'coverage_percentage' => $ruleData['coverage_percentage'] ?? 100,
                'minimum_collateral_value' => $ruleData['minimum_collateral_value'] ?? 0,
                'minimum_assets' => $ruleData['minimum_assets'] ?? 1,
                'maximum_assets' => $ruleData['maximum_assets'] ?? 1,
                'allowed_collateral_types' => $ruleData['allowed_collateral_types'] ?? null,
                'required_document_types' => $ruleData['required_document_types'] ?? null,
                'description' => $ruleData['description'] ?? null,
                'status' => !empty($ruleData['status']),
            ];

            if (!empty($ruleData['id'])) {
                $rule = LoanPlanCollateralRule::find($ruleData['id']);
                if ($rule && $rule->loan_plan_id === $loanPlan->id) {
                    $rule->update($data);
                }
            } else {
                $loanPlan->collateralRules()->create($data);
            }
        }
    }
}
