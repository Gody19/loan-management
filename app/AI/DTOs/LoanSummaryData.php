<?php

namespace App\AI\DTOs;

/**
 * Authoritative loan record presentation. All financial figures come from the
 * stored loan columns ($principal_amount, $amount_paid, $outstanding_balance,
 * ...). Loan completion is always judged by the authoritative stored
 * outstanding_balance — never derived from installment counts.
 */
final class LoanSummaryData
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $loanNumber,
        public readonly string $status,
        public readonly string $memberNumber,
        public readonly string $memberName,
        public readonly ?string $planName,
        public readonly float $principalAmount,
        public readonly float $disbursedAmount,
        public readonly float $interestRate,
        public readonly ?string $interestMethod,
        public readonly int $termMonths,
        public readonly ?string $repaymentFrequency,
        public readonly float $totalInterest,
        public readonly float $totalAmount,
        public readonly float $processingFee,
        public readonly float $insuranceFee,
        public readonly float $amountPaid,
        public readonly float $outstandingBalance,
        public readonly int $gracePeriod,
        public readonly ?string $disbursementDate,
        public readonly ?string $maturityDate,
        public readonly ?string $nextPaymentDate,
        public readonly int $installmentsPaid,
        public readonly int $totalInstallments,
        public readonly ?string $createdAt = null,
        public readonly array $extra = [],
    ) {}

    public function toArray(): array
    {
        return array_merge($this->extra, [
            'loan_number' => $this->loanNumber,
            'status' => $this->status,
            'member_number' => $this->memberNumber,
            'member_name' => $this->memberName,
            'plan_name' => $this->planName,
            'principal_amount' => $this->principalAmount,
            'disbursed_amount' => $this->disbursedAmount,
            'interest_rate' => $this->interestRate,
            'interest_method' => $this->interestMethod,
            'term_months' => $this->termMonths,
            'repayment_frequency' => $this->repaymentFrequency,
            'total_interest' => $this->totalInterest,
            'total_amount' => $this->totalAmount,
            'processing_fee' => $this->processingFee,
            'insurance_fee' => $this->insuranceFee,
            'amount_paid' => $this->amountPaid,
            'outstanding_balance' => $this->outstandingBalance,
            'grace_period' => $this->gracePeriod,
            'disbursement_date' => $this->disbursementDate,
            'maturity_date' => $this->maturityDate,
            'next_payment_date' => $this->nextPaymentDate,
            'installments_paid' => $this->installmentsPaid,
            'total_installments' => $this->totalInstallments,
            'created_at' => $this->createdAt,
        ]);
    }
}