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
            $paymentMethodId, $referenceNumber, $notes, $idempotencyKey
        ) {
            $loan->lockForUpdate();

            $repayment = LoanRepayment::create([
                'loan_id' => $loan->id,
                'organization_id' => $loan->organization_id,
                'branch_id' => $loan->branch_id,
                'member_id' => $loan->member_id,
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
                'status' => LoanRepaymentStatus::Posted,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
            ]);

            $allocations = $this->allocationService->allocatePayment($loan, $amount, $repayment->id);

            $totalPrincipal = array_sum(array_map(fn($a) => (float) $a->principal_allocation, $allocations));
            $totalInterest = array_sum(array_map(fn($a) => (float) $a->interest_allocation, $allocations));
            $totalFee = array_sum(array_map(fn($a) => (float) $a->fee_allocation, $allocations));
            $totalAllocated = $totalPrincipal + $totalInterest + $totalFee;
            $overpaymentAmount = max(0, $amount - $totalAllocated);

            $repayment->update([
                'principal_portion' => round($totalPrincipal, 2),
                'interest_portion' => round($totalInterest, 2),
                'fee_portion' => round($totalFee, 2),
                'overpayment_amount' => round($overpaymentAmount, 2),
            ]);

            $newAmountPaid = (float) $loan->amount_paid + $amount;
            $newOutstanding = max(0, (float) $loan->outstanding_balance - $amount);

            $nextPayment = $loan->repaymentSchedule()
                ->whereIn('status', [
                    LoanScheduleInstallmentStatus::Pending->value,
                    LoanScheduleInstallmentStatus::Partial->value,
                    LoanScheduleInstallmentStatus::Overdue->value,
                ])
                ->orderBy('due_date')
                ->first();

            $loan->update([
                'amount_paid' => round($newAmountPaid, 2),
                'outstanding_balance' => round($newOutstanding, 2),
                'next_payment_date' => $nextPayment?->due_date,
                'installments_paid' => $loan->repaymentSchedule()
                    ->where('status', LoanScheduleInstallmentStatus::Paid)
                    ->count(),
            ]);

            $this->auditService->log('loan.repayment.posted', $repayment, [], $repayment->toArray());

            $this->accountingService->recordLoanRepayment($repayment->fresh());

            return $repayment->fresh();
        });
    }

    public function reverseRepayment(LoanRepayment $repayment, string $reason): LoanRepayment
    {
        if (!$repayment->status->isReversible()) {
            throw new \InvalidArgumentException('Only posted repayments can be reversed.');
        }

        return DB::transaction(function () use ($repayment, $reason) {
            $loan = $repayment->loan;
            $loan->lockForUpdate();

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

            $loan->update([
                'amount_paid' => round($newAmountPaid, 2),
                'outstanding_balance' => round(max(0, $newOutstanding), 2),
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
            ->orderBy('payment_date', 'desc');

        if ($search) {
            $query->search($search);
        }
        if ($status) {
            $query->byStatus($status);
        }

        return $query->paginate(15);
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
