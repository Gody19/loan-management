<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\LoanSummaryData;
use App\AI\Services\AiToolAccessService;
use App\Models\User;
use App\Services\LoanDelinquencyService;

/**
 * ai.loan.view — single loan record within the authorized scope. The presented
 * figures are the stored authoritative loan columns; days_past_due comes from
 * the authoritative LoanDelinquencyService (0 when nothing is overdue).
 */
class LoanViewTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
        private readonly LoanDelinquencyService $delinquency,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $loan = $this->access->resolveLoan($user, $context, (string) ($arguments['loan_number'] ?? ''));

        $daysPastDue = $this->delinquency->getDaysPastDue($loan);

        return (new LoanSummaryData(
            loanNumber: $loan->loan_number,
            status: $loan->status?->value ?? 'unknown',
            memberNumber: $loan->member?->member_number,
            memberName: $loan->member?->full_name,
            planName: $loan->loanPlan->name !== null ? $loan->loanPlan->name : null,
            principalAmount: (float) $loan->principal_amount,
            disbursedAmount: (float) $loan->disbursed_amount,
            interestRate: (float) $loan->interest_rate,
            interestMethod: $loan->interest_method?->value,
            termMonths: (int) $loan->term_months,
            repaymentFrequency: $loan->repayment_frequency?->value,
            totalInterest: (float) $loan->total_interest,
            totalAmount: (float) $loan->total_amount,
            processingFee: (float) $loan->processing_fee,
            insuranceFee: (float) $loan->insurance_fee,
            amountPaid: (float) $loan->amount_paid,
            outstandingBalance: (float) $loan->outstanding_balance,
            gracePeriod: (int) ($loan->grace_period ?? 0),
            disbursementDate: $loan->disbursement_date?->toDateString(),
            maturityDate: $loan->maturity_date?->toDateString(),
            nextPaymentDate: $loan->next_payment_date?->toDateString(),
            installmentsPaid: (int) ($loan->installments_paid ?? 0),
            totalInstallments: (int) ($loan->total_installments ?? 0),
            createdAt: $loan->created_at?->toISOString(),
            extra: ['days_past_due' => $daysPastDue],
        ))->toArray();
    }
}