<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanApplicationRequest;
use App\Models\LoanApplication;
use App\Models\Member;
use App\Models\LoanPlan;
use App\Models\Branch;
use App\Models\VicobaGroup;
use App\Services\LoanApplicationService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class LoanApplicationController extends Controller
{
    public function __construct(
        private LoanApplicationService $loanAppService,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LoanApplication::class);

        $query = LoanApplication::with(['member', 'loanPlan', 'branch', 'vicobaGroup']);

        if (!$request->user()->hasRole('Super Administrator')) {
            $orgIds = $request->user()->organizations()->pluck('organizations.id')->toArray();
            if (!empty($orgIds)) {
                $query->whereHas('organization', function ($q) use ($orgIds) {
                    $q->whereIn('id', $orgIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $applications = $query->latest()->paginate(15)->withQueryString();
        $branches = Branch::active()->get();
        $organizations = \App\Models\Organization::active()->get();

        return view('loan-applications.index', compact('applications', 'branches', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', LoanApplication::class);

        $members = Member::active()->get();
        $loanPlans = LoanPlan::active()->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::active()->get();

        return view('loan-applications.create', compact('members', 'loanPlans', 'branches', 'groups'));
    }

    public function store(StoreLoanApplicationRequest $request)
    {
        $member = Member::findOrFail($request->member_id);
        $plan = LoanPlan::findOrFail($request->loan_plan_id);

        // Tenant validation
        if (!$request->user()->hasRole('Super Administrator')) {
            if (!$request->user()->organizations()->where('organizations.id', $member->organization_id)->exists()) {
                abort(403, 'Member does not belong to your organization.');
            }
        }

        if ($member->organization_id !== $plan->organization_id) {
            abort(422, 'Loan plan and member must belong to the same organization.');
        }

        $application = $this->loanAppService->create($request->validated(), $member, $plan);

        return redirect()->route('loan-applications.show', $application)
            ->with('success', 'Application ' . $application->application_number . ' created.');
    }

    public function show(LoanApplication $loanApplication)
    {
        $this->authorize('view', $loanApplication);
        $loanApplication->load(['member', 'loanPlan', 'branch', 'vicobaGroup', 'guarantors.guarantorMember', 'collaterals', 'approvals.actor', 'creator']);

        return view('loan-applications.show', ['application' => $loanApplication]);
    }

    public function edit(LoanApplication $loanApplication)
    {
        $this->authorize('update', $loanApplication);

        if ($loanApplication->status->isTerminal() || $loanApplication->status === \App\Enums\LoanApplicationStatus::Submitted) {
            abort(422, 'Application cannot be edited in its current status.');
        }

        $members = Member::active()->where('organization_id', $loanApplication->organization_id)->get();
        $loanPlans = LoanPlan::active()->where('organization_id', $loanApplication->organization_id)->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::active()->get();

        return view('loan-applications.edit', [
            'application' => $loanApplication,
            'members' => $members,
            'loanPlans' => $loanPlans,
            'branches' => $branches,
            'groups' => $groups,
        ]);
    }

    public function update(StoreLoanApplicationRequest $request, LoanApplication $loanApplication)
    {
        $this->authorize('update', $loanApplication);

        if ($loanApplication->status->isTerminal() || $loanApplication->status === \App\Enums\LoanApplicationStatus::Submitted) {
            abort(422, 'Application cannot be edited in its current status.');
        }

        $member = Member::findOrFail($request->member_id);
        $plan = LoanPlan::findOrFail($request->loan_plan_id);

        $loanApplication->update($request->validated());

        return redirect()->route('loan-applications.show', $loanApplication)
            ->with('success', 'Application updated.');
    }

    public function submit(LoanApplication $loanApplication)
    {
        $this->authorize('submit', $loanApplication);

        try {
            $this->loanAppService->submit($loanApplication);
            return redirect()->route('loan-applications.show', $loanApplication)
                ->with('success', 'Application submitted successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(LoanApplication $loanApplication, Request $request)
    {
        $this->authorize('cancel', $loanApplication);

        $request->validate(['cancellation_reason' => 'required|string|max:1000']);

        try {
            $this->loanAppService->cancel($loanApplication, $request->cancellation_reason);
            return redirect()->route('loan-applications.show', $loanApplication)
                ->with('success', 'Application cancelled.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function review(LoanApplication $loanApplication)
    {
        $this->authorize('review', $loanApplication);

        try {
            $this->loanAppService->addToReview($loanApplication);
            return redirect()->route('loan-applications.show', $loanApplication)
                ->with('success', 'Application moved to review.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(LoanApplication $loanApplication, Request $request)
    {
        $this->authorize('approve', $loanApplication);

        try {
            app(\App\Services\LoanApprovalService::class)->approve($loanApplication, $request->comments);
            return redirect()->route('loan-applications.show', $loanApplication)
                ->with('success', 'Application approved.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(LoanApplication $loanApplication, Request $request)
    {
        $this->authorize('reject', $loanApplication);

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'comments' => 'nullable|string|max:1000',
        ]);

        try {
            app(\App\Services\LoanApprovalService::class)->reject($loanApplication, $request->rejection_reason, $request->comments);
            return redirect()->route('loan-applications.show', $loanApplication)
                ->with('success', 'Application rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
