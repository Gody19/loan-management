<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepaymentRequest;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Services\LoanRepaymentScheduleService;
use App\Services\LoanRepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanRepaymentCollectionController extends Controller
{
    public function __construct(
        private LoanRepaymentService $repaymentService,
        private LoanRepaymentScheduleService $scheduleService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LoanRepayment::class);

        $search = $request->input('search');
        $user = $request->user();

        $query = Member::active()
            ->whereHas('loans', function ($q) {
                $q->where('status', 'active')
                    ->where('outstanding_balance', '>', 0);
            })
            ->with(['vicobaGroup', 'loans' => function ($q) {
                $q->where('status', 'active')
                    ->where('outstanding_balance', '>', 0)
                    ->select(['id', 'member_id', 'loan_number', 'outstanding_balance', 'amount_paid', 'principal_amount']);
            }]);

        if (! $user->hasRole('Super Administrator')) {
            $orgIds = $user->organizations()->pluck('organizations.id');
            $query->whereIn('members.organization_id', $orgIds);
        }

        if ($search) {
            $query->search($search);
        }

        $members = $query->get();

        return view('loan-repayments.collection.index', compact('search', 'members'));
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LoanRepayment::class);

        $search = $request->input('search', '');
        if (strlen($search) < 2) {
            return response()->json(['members' => []]);
        }

        $user = $request->user();
        $query = Member::search($search)->active()
            ->with('vicobaGroup')
            ->select(['id', 'member_number', 'first_name', 'middle_name', 'last_name', 'phone', 'organization_id', 'vicoba_group_id']);

        if (! $user->hasRole('Super Administrator')) {
            $orgIds = $user->organizations()->pluck('organizations.id');
            $query->whereIn('organization_id', $orgIds);
        }

        $members = $query->limit(15)->get()->map(fn ($m) => [
            'id' => $m->id,
            'member_number' => $m->member_number,
            'full_name' => $m->full_name,
            'phone' => $m->phone,
            'group_name' => $m->vicobaGroup?->name ?? 'N/A',
            'active_loans' => $m->loans()->where('status', 'active')->count(),
        ]);

        return response()->json(['members' => $members]);
    }

    public function memberLoans(Member $member)
    {
        $this->authorize('viewAny', LoanRepayment::class);

        $user = request()->user();
        if (! $user->hasRole('Super Administrator')) {
            abort_unless(
                $user->organizations()->where('organizations.id', $member->organization_id)->exists(),
                403
            );
        }

        $loans = Loan::where('member_id', $member->id)
            ->whereIn('status', ['active', 'pending_disbursement'])
            ->with('loanPlan')
            ->orderByDesc('created_at')
            ->get();

        $member->load('vicobaGroup', 'organization');

        return view('loan-repayments.collection.loans', compact('member', 'loans'));
    }

    public function createRepayment(Loan $loan)
    {
        $this->authorize('create', LoanRepayment::class);

        $user = request()->user();
        if (! $user->hasRole('Super Administrator')) {
            abort_unless(
                $user->organizations()->where('organizations.id', $loan->organization_id)->exists(),
                403
            );
        }

        $loan->load(['member', 'loanPlan', 'branch', 'repaymentSchedule' => function ($q) {
            $q->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('due_date');
        }]);

        $scheduleSummary = $this->scheduleService->getScheduleSummary($loan);

        $paymentMethods = \App\Models\PaymentMethod::where('organization_id', $loan->organization_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('loan-repayments.collection.create', compact('loan', 'scheduleSummary', 'paymentMethods'));
    }

    public function storeRepayment(StoreRepaymentRequest $request, Loan $loan)
    {
        $this->authorize('create', LoanRepayment::class);

        $user = $request->user();
        if (! $user->hasRole('Super Administrator')) {
            abort_unless(
                $user->organizations()->where('organizations.id', $loan->organization_id)->exists(),
                403
            );
        }

        try {
            $repayment = $this->repaymentService->postRepayment(
                $loan,
                (float) $request->amount,
                $request->payment_date,
                $request->payment_method,
                $request->payment_method_id,
                $request->reference_number,
                $request->notes,
                $request->idempotency_key,
            );

            return redirect()->route('loan-repayments-collection.member-loans', $loan->member_id)
                ->with('success', 'Payment recorded successfully. Repayment #' . $repayment->repayment_number);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
