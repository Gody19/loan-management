<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class AccountingConfigurationService
{
    public function __construct(
        private readonly ChartOfAccountsService $chartService,
    ) {}

    public const MAPPING_KEYS = [
        'cash_on_hand' => 'Cash on Hand',
        'bank_account' => 'Bank Account',
        'mobile_money' => 'Mobile Money',
        'loans_receivable' => 'Loans Receivable',
        'interest_receivable' => 'Interest Receivable',
        'fees_receivable' => 'Fees Receivable',
        'member_savings' => 'Member Savings',
        'welfare_funds_payable' => 'Welfare Funds Payable',
        'share_capital' => 'Share Capital',
        'retained_earnings' => 'Retained Earnings',
        'loan_interest_income' => 'Loan Interest Income',
        'loan_fees_income' => 'Loan Fees Income',
        'other_income' => 'Other Financial Income',
        'operating_expenses' => 'Operating Expenses',
    ];

    public function setMapping(int $organizationId, string $key, int $accountId): AccountMapping
    {
        if (!in_array($key, array_keys(self::MAPPING_KEYS))) {
            throw new \InvalidArgumentException("Invalid mapping key: {$key}");
        }

        $account = ChartOfAccount::where('id', $accountId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$account) {
            throw new \InvalidArgumentException('Account does not belong to this organization.');
        }

        return AccountMapping::updateOrCreate(
            ['organization_id' => $organizationId, 'mapping_key' => $key],
            ['chart_of_account_id' => $accountId],
        );
    }

    public function getMapping(int $organizationId, string $key): ?ChartOfAccount
    {
        $mapping = AccountMapping::forOrganization($organizationId)
            ->forKey($key)
            ->first();

        return $mapping?->account;
    }

    public function getAccountId(int $organizationId, string $key): int
    {
        $account = $this->getMapping($organizationId, $key);
        if (!$account) {
            $chartExists = \App\Models\ChartOfAccount::where('organization_id', $organizationId)->exists();
            if (!$chartExists) {
                $this->chartService->initializeDefaultChart($organizationId);
            }
            $this->initializeDefaultMappings($organizationId);
            $account = $this->getMapping($organizationId, $key);
        }
        if (!$account) {
            throw new \InvalidArgumentException("Account mapping not configured for: {$key}. Please configure accounting settings.");
        }
        return $account->id;
    }

    public function getAllMappings(int $organizationId): array
    {
        $mappings = AccountMapping::forOrganization($organizationId)
            ->with('account')
            ->get()
            ->keyBy('mapping_key');

        $result = [];
        foreach (self::MAPPING_KEYS as $key => $label) {
            $result[$key] = [
                'label' => $label,
                'account' => $mappings[$key]->account ?? null,
            ];
        }

        return $result;
    }

    public function initializeDefaultMappings(int $organizationId): void
    {
        $defaults = [
            'cash_on_hand' => '1100',
            'bank_account' => '1200',
            'mobile_money' => '1300',
            'loans_receivable' => '1400',
            'interest_receivable' => '1500',
            'fees_receivable' => '1600',
            'member_savings' => '2100',
            'welfare_funds_payable' => '2200',
            'share_capital' => '2300',
            'retained_earnings' => '3100',
            'loan_interest_income' => '4100',
            'loan_fees_income' => '4200',
            'other_income' => '4300',
            'operating_expenses' => '5100',
        ];

        foreach ($defaults as $key => $accountCode) {
            $account = ChartOfAccount::where('organization_id', $organizationId)
                ->where('account_code', $accountCode)
                ->first();

            if ($account) {
                AccountMapping::updateOrCreate(
                    ['organization_id' => $organizationId, 'mapping_key' => $key],
                    ['chart_of_account_id' => $account->id],
                );
            }
        }
    }
}
