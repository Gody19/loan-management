<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Http\Requests\StoreMemberLoanRepaymentRequest;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PaymentMethod;
use App\Services\LoanRepaymentScheduleService;
use App\Services\LoanRepaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberRepaymentController extends Controller
{
    public function __construct(
        private LoanRepaymentService $repaymentService,
        private LoanRepaymentScheduleService $scheduleService,
    ) {}

    public function loanDetail(Request $request, Loan $loan): View
    {
        $member = $request->user()->member;

        if ($loan->member_id !== $member->id) {
            abort(404);
        }

        $loan->load(['loanPlan', 'repaymentSchedule', 'repayments' => function ($q) {
            $q->orderBy('payment_date', 'desc')->limit(10);
        }]);

        $scheduleSummary = $this->scheduleService->getScheduleSummary($loan);

        return view('member.loans.show', compact('member', 'loan', 'scheduleSummary'));
    }

    public function repaymentSchedule(Request $request, Loan $loan): View
    {
        $member = $request->user()->member;

        if ($loan->member_id !== $member->id) {
            abort(404);
        }

        $loan->load(['loanPlan', 'repaymentSchedule']);

        $scheduleSummary = $this->scheduleService->getScheduleSummary($loan);

        return view('member.loans.schedule', compact('member', 'loan', 'scheduleSummary'));
    }

    public function statement(Request $request, Loan $loan): View
    {
        $member = $request->user()->member;

        if ($loan->member_id !== $member->id) {
            abort(404);
        }

        $loan->load(['loanPlan']);

        $repayments = LoanRepayment::where('loan_id', $loan->id)
            ->where('member_id', $member->id)
            ->with('allocations.installment')
            ->orderBy('payment_date', 'asc')
            ->get();

        $statementLines = collect();

        $statementLines->push([
            'date' => $loan->disbursement_date?->format('Y-m-d'),
            'description' => 'Loan Disbursement',
            'debit' => (float) $loan->disbursed_amount,
            'credit' => 0,
            'balance' => (float) $loan->disbursed_amount,
            'reference' => $loan->loan_number,
        ]);

        $runningBalance = (float) $loan->disbursed_amount;

        foreach ($repayments as $repayment) {
            if ($repayment->status->value !== 'posted') {
                $statementLines->push([
                    'date' => $repayment->payment_date?->format('Y-m-d'),
                    'description' => 'Payment ('.$repayment->status->label().')',
                    'debit' => 0,
                    'credit' => (float) $repayment->amount,
                    'balance' => $runningBalance,
                    'reference' => $repayment->repayment_number,
                    'reversed' => true,
                ]);
                continue;
            }

            $runningBalance -= (float) $repayment->amount;

            $statementLines->push([
                'date' => $repayment->payment_date?->format('Y-m-d'),
                'description' => 'Repayment',
                'debit' => 0,
                'credit' => (float) $repayment->amount,
                'balance' => max(0, $runningBalance),
                'reference' => $repayment->repayment_number,
            ]);
        }

        $statementLines = $statementLines->sortBy('date')->values();

        return view('member.loans.statement', compact('member', 'loan', 'statementLines'));
    }

    public function makePayment(Request $request, Loan $loan): View
    {
        $member = $request->user()->member;

        if ($loan->member_id !== $member->id) {
            abort(404);
        }

        if (!in_array($loan->status->value, ['active', 'pending_disbursement'])) {
            return back()->withErrors(['error' => 'Only active loans can accept repayments.']);
        }

        if ((float) $loan->outstanding_balance <= 0) {
            return back()->withErrors(['error' => 'This loan is fully paid. No further repayments accepted.']);
        }

        $loan->load(['loanPlan', 'repaymentSchedule' => function ($q) {
            $q->whereIn('status', ['pending', 'partial', 'overdue'])
              ->orderBy('due_date');
        }]);

        $paymentMethods = PaymentMethod::where('organization_id', $member->organization_id)
            ->where('status', 'active')
            ->get();

        return view('member.loans.repay', compact('member', 'loan', 'paymentMethods'));
    }

    public function selectLoan(Request $request): View
    {
        $member = $request->user()->member;

        $loans = Loan::where('member_id', $member->id)
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Disbursed, LoanStatus::PendingDisbursement])
            ->with(['loanPlan', 'branch'])
            ->orderBy('created_at', 'desc')
            ->get();

        $scheduleService = $this->scheduleService;

        return view('member.loans.repay-select', compact('member', 'loans', 'scheduleService'));
    }

    public function storeRepayment(StoreMemberLoanRepaymentRequest $request, Loan $loan): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loan->member_id !== $member->id) {
            abort(404);
        }

        if ($request->has('payment_method_id') && $request->payment_method_id) {
            $paymentMethod = PaymentMethod::where('id', $request->payment_method_id)
                ->where('organization_id', $member->organization_id)
                ->where('status', 'active')
                ->first();

            if (! $paymentMethod) {
                return back()->withInput()->withErrors(['payment_method_id' => 'Selected payment method is not valid for your organization.']);
            }
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
                null,
            );

            return redirect()->route('member.repayments.show', $repayment)
                ->with('success', 'Payment recorded successfully. Repayment #'.$repayment->repayment_number);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function repaymentHistory(Request $request): View
    {
        $member = $request->user()->member;

        $repayments = LoanRepayment::where('member_id', $member->id)
            ->where('organization_id', $member->organization_id)
            ->with(['loan', 'paymentMethod'])
            ->orderBy('payment_date', 'desc')
            ->paginate(15);

        return view('member.repayments.index', compact('member', 'repayments'));
    }

    public function showRepayment(Request $request, LoanRepayment $repayment): View
    {
        $member = $request->user()->member;

        if ($repayment->member_id !== $member->id) {
            abort(404);
        }

        $repayment->load([
            'loan.loanPlan', 'loan.member',
            'paymentMethod', 'allocations.installment',
        ]);

        return view('member.repayments.show', compact('member', 'repayment'));
    }

    public function mySchedule(Request $request): View
    {
        $member = $request->user()->member;

        $activeLoans = $member->loans()
            ->whereIn('status', ['active', 'disbursed', 'pending_disbursement'])
            ->with(['loanPlan', 'repaymentSchedule' => function ($q) {
                $q->orderBy('installment_number');
            }])
            ->get();

        $scheduleSummary = null;
        if ($activeLoans->count() === 1) {
            $scheduleSummary = $this->scheduleService->getScheduleSummary($activeLoans->first());
        }

        return view('member.my-schedule', compact('member', 'activeLoans', 'scheduleSummary'));
    }
}
