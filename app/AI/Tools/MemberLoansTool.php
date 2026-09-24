<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\LoanSummaryData;
use App\AI\Services\AiToolAccessService;
use App\Models\Loan;
use App\Models\User;

/**
 * ai.member.loans — bounded list of a member's loans. Each loan is presented
 * from its stored authoritative balances; no derived figures are invented.
 * Results are server-side bounded to at most 25 loans.
 */
class MemberLoansTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $member = $this->access->resolveMember($user, $context, $arguments['member_number'] ?? null);

        $limit = $this->access->boundedLimit($arguments['limit'] ?? null, 25, 10);

        $loans = $member->loans()->orderByDesc('id')->limit($limit)->get();

        $rows = $loans->map(fn (Loan $loan) => $this->loanRow($loan))->values()->all();

        return [
            'member_number' => $member->member_number,
            'member_name' => $member->full_name,
            'count' => $loans->count(),
            'limit' => $limit,
            'loans' => $rows,
        ];
    }

    private function loanRow(Loan $loan): array
    {
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
        ))->toArray();
    }
}