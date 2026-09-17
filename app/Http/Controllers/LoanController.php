<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Services\LoanDisbursementService;
use App\Services\LoanRepaymentScheduleService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(
        private LoanDisbursementService $loanService,
        private LoanRepaymentScheduleService $scheduleService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Loan::class);

        $user = $request->user();

        if ($user->hasRole('Super Administrator')) {
            $query = Loan::with(['member', 'loanPlan', 'branch']);
        } else {
            $orgIds = $user->organizations()->pluck('organizations.id')->toArray();
            if (! empty($orgIds)) {
                $query = Loan::whereIn('organization_id', $orgIds)
                    ->with(['member', 'loanPlan', 'branch']);
            } else {
                $query = Loan::whereRaw('1 = 0');
            }
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }
        if ($request->filled('organization_id')) {
            $query->forOrganization($request->organization_id);
        }
        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        $loans = $query->latest()->paginate(15)->withQueryString();

        return view('loans.index', compact('loans'));
    }

    public function show(Loan $loan)
    {
        $this->authorize('view', $loan);

        $loan->load([
            'member', 'loanPlan', 'branch', 'organization',
            'repaymentSchedule', 'disbursements.paymentMethod', 'disbursements.processor',
            'creator', 'disburser',
        ]);

        $scheduleSummary = $this->scheduleService->getScheduleSummary($loan);

        return view('loans.show', compact('loan', 'scheduleSummary'));
    }

    public function createFromApplication(LoanApplication $loanApplication)
    {
        $this->authorize('create', Loan::class);

        $this->authorize('view', $loanApplication);

        if (! $loanApplication->status || $loanApplication->status->value !== 'approved') {
            abort(422, 'Only approved applications can be converted to loans.');
        }

        $existingLoan = Loan::where('loan_application_id', $loanApplication->id)->first();
        if ($existingLoan) {
            return redirect()->route('loans.show', $existingLoan)
                ->with('info', 'A loan already exists for this application.');
        }

        try {
            $loan = $this->loanService->createLoanFromApplication($loanApplication);
            return redirect()->route('loans.show', $loan)
                ->with('success', 'Loan '.$loan->loan_number.' created from application.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Loan $loan, Request $request)
    {
        $this->authorize('cancel', $loan);

        $request->validate(['reason' => 'nullable|string|max:1000']);

        try {
            $this->loanService->cancelLoan($loan, $request->reason);
            return redirect()->route('loans.show', $loan)
                ->with('success', 'Loan cancelled.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function schedule(Loan $loan)
    {
        $this->authorize('viewSchedule', $loan);

        $loan->load('repaymentSchedule');

        return view('loans.schedule', compact('loan'));
    }
}
