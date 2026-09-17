<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepaymentRequest;
use App\Http\Requests\ReverseRepaymentRequest;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\LoanRepaymentService;

class LoanRepaymentController extends Controller
{
    public function __construct(
        private LoanRepaymentService $repaymentService,
    ) {}

    public function index(Loan $loan)
    {
        $this->authorize('viewAny', LoanRepayment::class);

        $repayments = $this->repaymentService->getRepaymentsForLoan($loan->id);

        $loan->load(['member', 'loanPlan', 'branch']);

        return view('loan-repayments.index', compact('loan', 'repayments'));
    }

    public function create(Loan $loan)
    {
        $this->authorize('create', LoanRepayment::class);

        $loan->load(['member', 'loanPlan', 'branch', 'repaymentSchedule' => function ($q) {
            $q->whereIn('status', ['pending', 'partial', 'overdue'])
              ->orderBy('due_date');
        }]);

        $scheduleSummary = app(\App\Services\LoanRepaymentScheduleService::class)
            ->getScheduleSummary($loan);

        return view('loan-repayments.create', compact('loan', 'scheduleSummary'));
    }

    public function store(StoreRepaymentRequest $request, Loan $loan)
    {
        $this->authorize('create', LoanRepayment::class);

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

            return redirect()->route('loan-repayments.show', $repayment)
                ->with('success', 'Payment recorded successfully. Repayment #' . $repayment->repayment_number);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(LoanRepayment $loanRepayment)
    {
        $this->authorize('view', $loanRepayment);

        $loanRepayment->load([
            'loan.member', 'loan.loanPlan', 'loan.branch',
            'receiver', 'paymentMethod',
            'allocations.installment',
        ]);

        return view('loan-repayments.show', compact('loanRepayment'));
    }

    public function reverse(LoanRepayment $loanRepayment, ReverseRepaymentRequest $request)
    {
        $this->authorize('reverse', $loanRepayment);

        try {
            $this->repaymentService->reverseRepayment($loanRepayment, $request->reason);

            return redirect()->route('loan-repayments.show', $loanRepayment)
                ->with('success', 'Payment reversed successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
