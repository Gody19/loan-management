<?php

namespace App\Services;

use App\Enums\LoanDisbursementStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoanDisbursementService
{
    public function __construct(
        private readonly LoanRepaymentScheduleService $scheduleService,
        private readonly LoanNumberGenerator $loanNumberGenerator,
        private readonly DisbursementNumberGenerator $disbursementNumberGenerator,
        private readonly AuditService $auditService,
    ) {}

    public function createLoanFromApplication(LoanApplication $application): Loan
    {
        $this->validateApplicationForLoan($application);

        $loanNumber = $this->loanNumberGenerator->generate();
        $plan = $application->loanPlan;

        $periodsPerYear = $application->repayment_frequency->periodsPerYear();
        $totalInstallments = (int) ceil($application->requested_term * $periodsPerYear / 12);

        return DB::transaction(function () use ($application, $loanNumber, $totalInstallments) {
            $loan = Loan::create([
                'organization_id' => $application->organization_id,
                'branch_id' => $application->branch_id,
                'member_id' => $application->member_id,
                'loan_plan_id' => $application->loan_plan_id,
                'loan_application_id' => $application->id,
                'loan_number' => $loanNumber,
                'principal_amount' => $application->requested_amount,
                'disbursed_amount' => $application->requested_amount,
                'interest_rate' => $application->loanPlan->interest_rate,
                'interest_method' => $application->loanPlan->interest_method,
                'term_months' => $application->requested_term,
                'repayment_frequency' => $application->repayment_frequency,
                'grace_period' => $application->loanPlan->grace_period,
                'processing_fee' => $application->loanPlan->processing_fee,
                'insurance_fee' => $application->loanPlan->insurance_fee,
                'total_installments' => $totalInstallments,
                'status' => LoanStatus::PendingDisbursement,
            ]);

            $this->scheduleService->generateSchedule($loan);

            $this->auditService->log('loan.created', $loan, [], $loan->toArray());

            return $loan->fresh();
        });
    }

    public function createDisbursementRecord(
        Loan $loan,
        float $amount,
        string $disbursementMethod,
        ?int $paymentMethodId,
        ?string $referenceNumber,
        ?string $notes,
    ): LoanDisbursement {
        $this->validateLoanForDisbursement($loan);

        $netAmount = $amount - (float) $loan->processing_fee - (float) $loan->insurance_fee;

        return DB::transaction(function () use ($loan, $amount, $disbursementMethod, $paymentMethodId, $referenceNumber, $notes, $netAmount) {
            $disbursement = LoanDisbursement::create([
                'loan_id' => $loan->id,
                'organization_id' => $loan->organization_id,
                'branch_id' => $loan->branch_id,
                'payment_method_id' => $paymentMethodId,
                'processed_by' => auth()->id(),
                'disbursement_number' => $this->disbursementNumberGenerator->generate(),
                'amount' => $amount,
                'processing_fee' => $loan->processing_fee,
                'insurance_fee' => $loan->insurance_fee,
                'net_amount' => round($netAmount, 2),
                'disbursement_date' => now()->toDateString(),
                'status' => LoanDisbursementStatus::Pending,
                'disbursement_method' => $disbursementMethod,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
            ]);

            $this->auditService->log('loan.disbursement.created', $disbursement, [], $disbursement->toArray());

            return $disbursement;
        });
    }

    public function confirmDisbursement(LoanDisbursement $disbursement, User $processor): LoanDisbursement
    {
        if ($disbursement->status !== LoanDisbursementStatus::Pending) {
            throw new \InvalidArgumentException('Disbursement is not in pending status.');
        }

        return DB::transaction(function () use ($disbursement, $processor) {
            $loan = $disbursement->loan;

            $disbursement->update([
                'status' => LoanDisbursementStatus::Confirmed,
                'processed_by' => $processor->id,
            ]);

            $schedule = $this->scheduleService->getScheduleSummary($loan);
            $nextPayment = $loan->repaymentSchedule()
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->first();

            $loan->update([
                'status' => LoanStatus::Active,
                'disbursed_by' => $processor->id,
                'disbursement_date' => $disbursement->disbursement_date,
                'maturity_date' => $loan->repaymentSchedule()->max('due_date'),
                'next_payment_date' => $nextPayment?->due_date,
                'amount_paid' => 0,
                'outstanding_balance' => $loan->principal_amount,
            ]);

            $this->auditService->log('loan.disbursement.confirmed', $disbursement, [
                'status' => LoanDisbursementStatus::Pending->value,
            ], [
                'status' => LoanDisbursementStatus::Confirmed->value,
            ]);

            $this->auditService->log('loan.activated', $loan, [
                'status' => LoanStatus::PendingDisbursement->value,
            ], [
                'status' => LoanStatus::Active->value,
            ]);

            return $disbursement->fresh();
        });
    }

    public function rejectDisbursement(LoanDisbursement $disbursement, string $reason, User $processor): LoanDisbursement
    {
        if ($disbursement->status !== LoanDisbursementStatus::Pending) {
            throw new \InvalidArgumentException('Disbursement is not in pending status.');
        }

        return DB::transaction(function () use ($disbursement, $reason, $processor) {
            $disbursement->update([
                'status' => LoanDisbursementStatus::Rejected,
                'processed_by' => $processor->id,
                'rejection_reason' => $reason,
            ]);

            $this->auditService->log('loan.disbursement.rejected', $disbursement, [
                'status' => LoanDisbursementStatus::Pending->value,
            ], [
                'status' => LoanDisbursementStatus::Rejected->value,
            ]);

            return $disbursement->fresh();
        });
    }

    public function cancelLoan(Loan $loan, ?string $reason = null): Loan
    {
        if ($loan->status->isTerminal()) {
            throw new \InvalidArgumentException('Cannot cancel a loan that is already ' . $loan->status->label() . '.');
        }

        return DB::transaction(function () use ($loan, $reason) {
            $oldStatus = $loan->status;

            $loan->update([
                'status' => LoanStatus::Cancelled,
                'notes' => $reason ?? $loan->notes,
            ]);

            $loan->repaymentSchedule()->update(['status' => 'waived']);

            $this->auditService->log('loan.cancelled', $loan, [
                'status' => $oldStatus->value,
            ], [
                'status' => LoanStatus::Cancelled->value,
                'cancellation_reason' => $reason,
            ]);

            return $loan->fresh();
        });
    }

    public function getLoansForOrganization(int $organizationId, ?string $status = null, ?string $search = null)
    {
        $query = Loan::forOrganization($organizationId)
            ->with(['member', 'loanPlan', 'branch'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->byStatus($status);
        }

        if ($search) {
            $query->search($search);
        }

        return $query->paginate(15);
    }

    public function getLoansForBranch(int $branchId, ?string $status = null, ?string $search = null)
    {
        $query = Loan::forBranch($branchId)
            ->with(['member', 'loanPlan', 'branch'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->byStatus($status);
        }

        if ($search) {
            $query->search($search);
        }

        return $query->paginate(15);
    }

    public function getLoansForMember(int $memberId)
    {
        return Loan::where('member_id', $memberId)
            ->with(['loanPlan', 'repaymentSchedule'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    private function validateApplicationForLoan(LoanApplication $application): void
    {
        if ($application->status->value !== 'approved') {
            throw new \InvalidArgumentException('Loan application must be approved before creating a loan.');
        }

        $existingLoan = Loan::where('loan_application_id', $application->id)->first();
        if ($existingLoan) {
            throw new \InvalidArgumentException('A loan already exists for this application.');
        }
    }

    private function validateLoanForDisbursement(Loan $loan): void
    {
        if ($loan->status !== LoanStatus::PendingDisbursement) {
            throw new \InvalidArgumentException('Loan must be in pending disbursement status.');
        }
    }
}
