<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Services\LoanEligibilityService;
use Illuminate\Http\Request;

class LoanEligibilityController extends Controller
{
    public function __construct(private LoanEligibilityService $eligibilityService) {}

    public function index(Request $request)
    {
        $this->authorize('view', LoanPlan::class);

        $query = Member::query();

        if (! $request->user()->hasRole('Super Administrator')) {
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
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'LIKE', "%{$request->search}%")
                    ->orWhere('last_name', 'LIKE', "%{$request->search}%")
                    ->orWhere('member_number', 'LIKE', "%{$request->search}%");
            });
        }

        $members = $query->where('membership_status', MemberStatus::Active)->latest()->paginate(15)->withQueryString();

        return view('loan-eligibility.index', compact('members'));
    }

    public function check(Request $request)
    {
        $this->authorize('view', LoanPlan::class);

        $request->validate([
            'member_id' => 'required|exists:members,id',
            'loan_plan_id' => 'required|exists:loan_plans,id',
            'requested_amount' => 'required|numeric|min:1',
            'term_months' => 'nullable|integer|min:1',
        ]);

        $member = Member::findOrFail($request->member_id);
        $plan = LoanPlan::findOrFail($request->loan_plan_id);

        $result = $this->eligibilityService->checkEligibility(
            member: $member,
            plan: $plan,
            requestedAmount: (float) $request->requested_amount,
            termMonths: $request->integer('term_months'),
        );

        $loanPlans = LoanPlan::active()->get();
        $activeMember = $member;

        return view('loan-eligibility.result', compact('result', 'loanPlans', 'activeMember'));
    }
}
