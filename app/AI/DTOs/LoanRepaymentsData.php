<?php

namespace App\AI\DTOs;

/**
 * Bounded repayment history for a loan. Posted and reversed repayments are
 * presented distinctly so the AI never misstates a reversed payment as
 * collected. Portions are the stored allocation figures.
 */
final class LoanRepaymentsData
{
    /**
     * @param  array<int, array<string, mixed>>  $repayments
     */
    public function __construct(
        public readonly string $loanNumber,
        public readonly int $totalCount,
        public readonly int $postedCount,
        public readonly int $reversedCount,
        public readonly array $repayments,
    ) {}

    public function toArray(): array
    {
        return [
            'loan_number' => $this->loanNumber,
            'total_count' => $this->totalCount,
            'posted_count' => $this->postedCount,
            'reversed_count' => $this->reversedCount,
            'repayments' => $this->repayments,
        ];
    }
}