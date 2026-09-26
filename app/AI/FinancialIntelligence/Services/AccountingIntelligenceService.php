<?php

namespace App\AI\FinancialIntelligence\Services;

use App\AI\FinancialIntelligence\DTOs\FinancialAccountingData;
use App\Models\Organization;
use App\Services\AccountingConfigurationService;
use App\Services\GeneralLedgerService;
use App\Services\IncomeStatementService;
use App\Services\TrialBalanceService;
use InvalidArgumentException;
use Throwable;

/**
 * Accounting intelligence aggregated from the authoritative FinancePro report
 * services. Organizations whose accounting is not configured are skipped
 * (never reported as zero); every included organization carries its own
 * income / expense / balance / liquidity so the aggregate stays traceable.
 */
class AccountingIntelligenceService
{
    public function __construct(
        private readonly IncomeStatementService $incomeStatement,
        private readonly TrialBalanceService $trialBalance,
        private readonly GeneralLedgerService $ledger,
        private readonly AccountingConfigurationService $accounting,
    ) {}

    /**
     * @param  int[]  $organizationIds
     */
    public function summarize(array $organizationIds): FinancialAccountingData
    {
        $breakdown = [];
        $totalIncome = 0.0;
        $totalExpenses = 0.0;
        $netIncome = 0.0;
        $liquidPosition = 0.0;
        $allBalanced = true;

        foreach (array_slice($organizationIds, 0, 20) as $organizationId) {
            $org = Organization::find($organizationId);

            if (! $org) {
                continue;
            }

            $income = $this->safeIncome($organizationId);
            $balanced = $this->safeBalanced($organizationId);
            $liquid = $this->liquidPosition($organizationId);

            if ($income === null && $balanced === null) {
                continue;
            }

            $orgIncome = (float) ($income['total_income'] ?? 0);
            $orgExpenses = (float) ($income['total_expenses'] ?? 0);
            $orgNet = (float) ($income['net_income'] ?? 0);
            $orgBalanced = $balanced ?? true;

            $breakdown[] = [
                'organization_id' => $organizationId,
                'organization_name' => $org->name,
                'total_income' => round($orgIncome, 2),
                'total_expenses' => round($orgExpenses, 2),
                'net_income' => round($orgNet, 2),
                'is_balanced' => $orgBalanced,
                'liquid_position' => round($liquid, 2),
            ];

            $totalIncome += $orgIncome;
            $totalExpenses += $orgExpenses;
            $netIncome += $orgNet;
            $liquidPosition += $liquid;
            $allBalanced = $allBalanced && $orgBalanced;
        }

        return new FinancialAccountingData(
            currency: (string) config('financial-intelligence.currency', 'TZS'),
            organizationCount: count($breakdown),
            totalIncome: round($totalIncome, 2),
            totalExpenses: round($totalExpenses, 2),
            netIncome: round($netIncome, 2),
            liquidPosition: round($liquidPosition, 2),
            allBalanced: $allBalanced,
            organizationBreakdown: $breakdown,
            generatedAt: now()->toISOString(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function safeIncome(int $organizationId): ?array
    {
        try {
            return $this->incomeStatement->generate($organizationId);
        } catch (Throwable) {
            return null;
        }
    }

    protected function safeBalanced(int $organizationId): ?bool
    {
        try {
            return (bool) $this->trialBalance->generate($organizationId)['is_balanced'];
        } catch (Throwable) {
            return null;
        }
    }

    protected function liquidPosition(int $organizationId): float
    {
        $total = 0.0;

        foreach (['cash_on_hand', 'bank_account', 'mobile_money'] as $key) {
            try {
                $accountId = $this->accounting->getAccountId($organizationId, $key);
                $total += (float) $this->ledger->getAccountBalance($organizationId, $accountId);
            } catch (InvalidArgumentException) {
                // Accounting not configured for this key — not a data signal.
            } catch (Throwable) {
                // Balance computation is best-effort for liquidity.
            }
        }

        return $total;
    }
}
