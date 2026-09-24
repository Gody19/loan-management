<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\DTOs\LoanRepaymentsData;
use App\AI\Services\AiToolAccessService;
use App\Models\LoanRepayment;
use App\Models\User;

/**
 * ai.loan.repayments — bounded repayment history for a loan. Posted and
 * reversed repayments are presented distinctly so the AI never misstates a
 * reversed payment as collected. Results are server-side bounded to at most 50
 * repayments and optionally filtered by payment_date window.
 */
class LoanRepaymentsTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolAccessService $access,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $loan = $this->access->resolveLoan($user, $context, (string) ($arguments['loan_number'] ?? ''));

        $dates = $this->access->validateDateRange(
            $arguments['from'] ?? null,
            $arguments['to'] ?? null,
        );

        $limit = $this->access->boundedLimit($arguments['limit'] ?? null, 50, 20);

        $base = $loan->repayments();

        if ($dates['from'] !== null) {
            $base->where('payment_date', '>=', $dates['from']);
        }

        if ($dates['to'] !== null) {
            $base->where('payment_date', '<=', $dates['to']);
        }

        $totalCount = (clone $base)->count();
        $postedCount = (clone $base)->where('status', 'posted')->count();

        $repayments = (clone $base)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $rows = $repayments->map(function (LoanRepayment $repayment) {
            $isReversed = $repayment->status?->value === 'reversed';

            return [
                'repayment_number' => $repayment->repayment_number,
                'payment_date' => $repayment->payment_date?->toDateString(),
                'amount' => (float) $repayment->amount,
                'principal_portion' => (float) $repayment->principal_portion,
                'interest_portion' => (float) $repayment->interest_portion,
                'fee_portion' => (float) $repayment->fee_portion,
                'overpayment_amount' => (float) $repayment->overpayment_amount,
                'payment_method' => $repayment->payment_method,
                'status' => $repayment->status?->value,
                'is_reversed' => $isReversed,
                'reversal_reason' => $repayment->reversal_reason,
                'reversal_date' => $repayment->reversal_date?->toDateString(),
            ];
        })->values()->all();

        return (new LoanRepaymentsData(
            loanNumber: $loan->loan_number,
            totalCount: $totalCount,
            postedCount: $postedCount,
            reversedCount: $totalCount - $postedCount,
            repayments: $rows,
        ))->toArray();
    }
}