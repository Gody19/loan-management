<?php

namespace App\Services;

use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanRepayment;
use Illuminate\Support\Facades\DB;

class LoanRepaymentService
{
    public function __construct(
        private readonly RepaymentNumberGenerator $numberGenerator,
        private readonly LoanRepaymentAllocationService $allocationService,
        private readonly AuditService $auditService,
        private readonly AccountingEventService $accountingService,
    ) {}

    public function postRepayment(
        Loan $loan,
        float $amount,
        string $paymentDate,
        string $paymentMethod,
        ?int $paymentMethodId,
        ?string $referenceNumber,
        ?string $notes,
        ?string $idempotencyKey,
        bool $requiresApproval = false,
    ): LoanRepayment {
        $this->validateLoanForRepayment($loan);

        if ($idempotencyKey) {
            $existing = LoanRepayment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                throw new \InvalidArgumentException('Duplicate payment detected.');
            }
        }

        return DB::transaction(function () use (
            $loan, $amount, $paymentDate, $paymentMethod,
            $paymentMethodId, $referenceNumber, $notes, $idempotencyKey, $requiresApproval
        ) {
            // Re-read the loan under a row lock and use THAT row for every
            // subsequent read and write in this transaction.
            //
            // Calling lockForUpdate() on the model instance is a silent no-op:
            // Eloquent forwards it to a throwaway query builder, which only sets
            // a lock flag that is discarded because nothing is ever executed.
            // Without this lock two concurrent repayments both read the same
            // outstanding_balance, both pass validation, and both write, which
            // over-collects the loan and corrupts its schedule. This mirrors the
            // correct pattern already used by SavingsTransactionService.
            $lockedLoan = Loan::query()
                ->whereKey($loan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateLoanForRepayment($lockedLoan);

            $repayment = LoanRepayment::create([
                'loan_id' => $lockedLoan->id,
                'organization_id' => $lockedLoan->organization_id,
                'branch_id' => $lockedLoan->branch_id,
                'member_id' => $lockedLoan->member_id,
                'payment_method_id' => $paymentMethodId,
                'received_by' => auth()->id(),
                'repayment_number' => $this->numberGenerator->generate(),
                'amount' => round($amount, 2),
                'principal_portion' => 0,
                'interest_portion' => 0,
                'fee_portion' => 0,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'status' => $requiresApproval
                    ? LoanRepaymentStatus::Pending
                    : LoanRepaymentStatus::Posted,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
            ]);

            // Member-submitted payments are only recorded: the loan balance,
            // schedule and ledger stay untouched until an authorised user
            // approves the payment.
            if ($requiresApproval) {
                $this->auditService->log('loan.repayment.submitted', $repayment, [], $repayment->toArray());

                return $repayment->fresh();
            }

            return $this->applyRepayment($lockedLoan, $repayment, $amount);
        });
    }

    /**
     * Approve a member-submitted payment: allocate it to the schedule and
     * update the loan balance, schedule and ledger exactly like a manually
     * recorded payment.
     */
    public function approveRepayment(LoanRepayment $repayment): LoanRepayment
    {
        if (! $repayment->status->isAwaitingReview()) {
            throw new \InvalidArgumentException('Only pending payments can be approved.');
        }

        return DB::transaction(function () use ($repayment) {
            $lockedLoan = Loan::query()
                ->whereKey($repayment->loan_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateLoanForRepayment($lockedLoan);

            $lockedRepayment = LoanRepayment::query()
                ->whereKey($repayment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedLoan->status || ! $lockedLoan->status->isRepayable()) {
                throw new \InvalidArgumentException('This loan no longer accepts repayments.');
            }

            if (! $lockedLoan->status instanceof LoanStatus) {
                throw new \InvalidArgumentException('Loan must be active to accept repayments.');
            }

            if ($lockedLoan->status !== LoanStatus::Active) {
                throw new \InvalidArgumentException('Loan must be active to accept repayments.');
            }

            if ((float) $lockedLoan->outstanding_balance <= 0) {
                throw new \InvalidArgumentException('Loan is fully paid. This payment can no longer be approved.');
            }

            if (! $lockedLoan->status ?? true) {
                throw new \InvalidArgumentException('Only pending payments can be approved.');
            }

            $lockedLoan->refresh();

            $lockedLoan = Loan::query()
                ->whereKey($repayment->loan_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateLoanForRepayment($lockedLoan);

            $lockedRepayment->update(['status' => LoanRepaymentStatus::Posted]);

            $approved = $this->applyRepayment($lockedLoan, $lockedRepayment, (float) $lockedRepayment->amount);

            $this->auditService->log('loan.repayment.approved', $approved, [
                'status' => LoanRepaymentStatus::Pending->value,
            ], [
                'status' => LoanRepaymentStatus::Posted->value,
            ]);

            return $approved;
        });
    }

    /**
     * Reject a member-submitted payment. Nothing is applied to the loan.
     */
    public function rejectRepayment(LoanRepayment $repayment, string $reason): LoanRepayment
    {
        if (! $repayment->status->isAwaitingReview()) {
            throw new \InvalidArgumentException('Only pending payments can be rejected.');
        }

        $repayment->update([
            'status' => LoanRepaymentStatus::Rejected,
            'rejection_reason' => $reason,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ]);

        $this->auditService->log('loan.repayment.rejected', $repayment, [
            'status' => LoanRepaymentStatus::Pending->value,
        ], [
            'status' => LoanRepaymentStatus::Rejected->value,
            'rejection_reason' => $reason,
        ]);

        return $repayment->fresh();
    }

    /**
     * Apply an already-persisted repayment to the (locked) loan: allocate the
     * amount across installments, update the loan balances and status, and
     * post the accounting entry.
     */
    private function applyRepayment(Loan $lockedLoan, LoanRepayment $repayment, float $amount): LoanRepayment
    {
        $allocations = $this->allocationService->allocatePayment($lockedLoan, $amount, $repayment->id);

        $totalPrincipal = array_sum(array_map(fn ($a) => (float) $a->principal_allocation, $allocations));
        $totalInterest = array_sum(array_map(fn ($a) => (float) $a->interest_allocation, $allocations));
        $totalFee = array_sum(array_map(fn ($a) => (float) $a->fee_allocation, $allocations));
        $totalAllocated = $totalPrincipal + $totalInterest + $totalFee;
        $overpaymentAmount = max(0, $amount - $totalAllocated);

        $repayment->update([
            'status' => LoanRepaymentStatus::Posted,
            'principal_portion' => round($totalPrincipal, 2),
            'interest_portion' => round($totalInterest, 2),
            'fee_portion' => round($totalFee, 2),
            'overpayment_amount' => round($overpaymentAmount, 2),
        ]);

        // Read the balances from the locked row, not from the instance that
        // was loaded before the lock was taken, or the write below would
        // overwrite a concurrent repayment's result with a stale value.
        $newAmountPaid = (float) $lockedLoan->amount_paid + $amount;
        $newOutstanding = max(0, (float) $lockedLoan->outstanding_balance - $amount);

        $nextPayment = $lockedLoan->repaymentSchedule()
            ->whereIn('status', [
                LoanScheduleInstallmentStatus::Pending->value,
                LoanScheduleInstallmentStatus::Partial->value,
                LoanScheduleInstallmentStatus::Overdue->value,
            ])
            ->orderBy('due_date')
            ->first();

        $lockedLoan->update([
            'amount_paid' => round($newAmountPaid, 2),
            'outstanding_balance' => round($newOutstanding, 2),
            'next_payment_date' => $nextPayment?->due_date,
            'installments_paid' => $lockedLoan->repaymentSchedule()
                ->where('status', LoanScheduleInstallmentStatus::Paid)
                ->count(),
            'status' => $newOutstanding <= 0 ? LoanStatus::Completed : LoanStatus::Active,
        ]);

        $this->auditService->log('loan.repayment.posted', $repayment, [], $repayment->toArray());

        $this->accountingService->recordLoanRepayment($repayment->fresh());

        return $repayment->fresh();
    }

    public function reverseRepayment(LoanRepayment $repayment, string $reason): LoanRepayment
    {
        if (! $repayment->status->isReversible()) {
            throw new \InvalidArgumentException('Only posted repayments can be reversed.');
        }

        return DB::transaction(function () use ($repayment, $reason) {
            // Same defect as the post path: locking the already-loaded model
            // instance emits no SQL, so a reversal could race a concurrent
            // repayment and reverse allocations that had just been re-allocated.
            $loan = Loan::query()
                ->whereKey($repayment->loan_id)
                ->lockForUpdate()
                ->firstOrFail();

            $repayment->update([
                'status' => LoanRepaymentStatus::Reversed,
                'reversal_reason' => $reason,
                'reversed_by' => auth()->id(),
                'reversal_date' => now()->toDateString(),
            ]);

            $this->allocationService->reverseAllocations($repayment->id);

            $totalReversed = LoanRepayment::where('loan_id', $loan->id)
                ->where('status', LoanRepaymentStatus::Posted)
                ->sum('amount');

            $newAmountPaid = (float) $totalReversed;
            $newOutstanding = (float) $loan->principal_amount - $newAmountPaid;

            $openStatuses = [
                LoanScheduleInstallmentStatus::Pending->value,
                LoanScheduleInstallmentStatus::Partial->value,
                LoanScheduleInstallmentStatus::Overdue->value,
            ];

            $nextPayment = $loan->repaymentSchedule()
                ->whereIn('status', $openStatuses)
                ->orderBy('due_date')
                ->first();

            $installmentsPaid = $loan->repaymentSchedule()
                ->where('status', LoanScheduleInstallmentStatus::Paid)
                ->count();

            $reopenedToActive = $loan->status === LoanStatus::Completed && $newOutstanding > 0;

            $loan->update([
                'amount_paid' => round($newAmountPaid, 2),
                'outstanding_balance' => round(max(0, $newOutstanding), 2),
                'installments_paid' => $installmentsPaid,
                'next_payment_date' => $nextPayment?->due_date,
                'status' => $reopenedToActive ? LoanStatus::Active : $loan->status,
            ]);

            $this->auditService->log('loan.repayment.reversed', $repayment, [
                'status' => LoanRepaymentStatus::Posted->value,
            ], [
                'status' => LoanRepaymentStatus::Reversed->value,
                'reversal_reason' => $reason,
            ]);

            $this->accountingService->recordRepaymentReversal($repayment->fresh());

            return $repayment->fresh();
        });
    }

    public function getRepaymentsForLoan(int $loanId, ?string $status = null)
    {
        $query = LoanRepayment::forLoan($loanId)
            ->with(['receiver', 'paymentMethod', 'allocations.installment'])
            ->orderBy('payment_date', 'desc');

        if ($status) {
            $query->byStatus($status);
        }

        return $query->get();
    }

    public function getRepaymentsForOrganization(int $organizationId, ?string $search = null, ?string $status = null)
    {
        $query = LoanRepayment::forOrganization($organizationId)
            ->with(['loan', 'member', 'receiver', 'paymentMethod'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc');

        if ($search) {
            $query->search($search);
        }
        if ($status) {
            $query->byStatus($status);
        }

        return $query->paginate(15);
    }

    public function getPendingRepaymentsForOrganization(int $organizationId, ?string $search = null)
    {
        $query = LoanRepayment::forOrganization($organizationId)
            ->pending()
            ->with(['loan', 'member', 'receiver', 'paymentMethod'])
            ->orderBy('created_at', 'asc');

        if ($search) {
            $query->search($search);
        }

        return $query->paginate(15);
    }

    public function getRepaymentsForOrganizationIndex(int $organizationId, ?string $search = null, ?string $status = null)
    {
        return $this->getRepaymentsForOrganization($organizationId, $search, $status);
    }

    private function validateLoanForRepayment(Loan $loan): void
    {
        if ($loan->status !== LoanStatus::Active) {
            throw new \InvalidArgumentException('Loan must be active to accept repayments.');
        }

        if ((float) $loan->outstanding_balance <= 0) {
            throw new \InvalidArgumentException('Loan is fully paid. No further repayments accepted.');
        }
    }
}
