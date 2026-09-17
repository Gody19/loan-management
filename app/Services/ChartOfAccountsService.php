<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsService
{
    public function createAccount(
        int $organizationId,
        string $accountCode,
        string $accountName,
        AccountType $accountType,
        ?string $description = null,
        bool $isSystem = false,
        ?int $parentId = null,
        ?int $userId = null,
    ): ChartOfAccount {
        $existing = ChartOfAccount::forOrganization($organizationId)
            ->where('account_code', $accountCode)
            ->exists();

        if ($existing) {
            throw new \InvalidArgumentException("Account code '{$accountCode}' already exists for this organization.");
        }

        if ($parentId) {
            $parent = ChartOfAccount::where('id', $parentId)
                ->where('organization_id', $organizationId)
                ->first();

            if (!$parent) {
                throw new \InvalidArgumentException('Parent account does not belong to this organization.');
            }
        }

        return ChartOfAccount::create([
            'organization_id' => $organizationId,
            'parent_id' => $parentId,
            'account_code' => $accountCode,
            'account_name' => $accountName,
            'account_type' => $accountType,
            'description' => $description,
            'normal_balance' => $accountType->normalBalance(),
            'is_system' => $isSystem,
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }

    public function updateAccount(
        ChartOfAccount $account,
        array $data,
        ?int $userId = null,
    ): ChartOfAccount {
        if ($account->is_system && isset($data['account_type'])) {
            throw new \InvalidArgumentException('Cannot change the type of a system account.');
        }

        if (isset($data['account_code']) && $data['account_code'] !== $account->account_code) {
            $existing = ChartOfAccount::forOrganization($account->organization_id)
                ->where('account_code', $data['account_code'])
                ->where('id', '!=', $account->id)
                ->exists();

            if ($existing) {
                throw new \InvalidArgumentException("Account code '{$data['account_code']}' already exists.");
            }
        }

        $data['updated_by'] = $userId;
        $account->update($data);
        return $account->fresh();
    }

    public function deactivateAccount(ChartOfAccount $account): ChartOfAccount
    {
        if ($account->is_system) {
            throw new \InvalidArgumentException('Cannot deactivate a system account.');
        }

        $hasTransactions = $account->journalLines()
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted'))
            ->exists();

        if ($hasTransactions) {
            throw new \InvalidArgumentException('Cannot deactivate an account with posted transactions.');
        }

        $account->update(['is_active' => false]);
        return $account->fresh();
    }

    public function activateAccount(ChartOfAccount $account): ChartOfAccount
    {
        $account->update(['is_active' => true]);
        return $account->fresh();
    }

    public function deleteAccount(ChartOfAccount $account): void
    {
        if ($account->is_system) {
            throw new \InvalidArgumentException('Cannot delete a system account.');
        }

        $hasTransactions = $account->journalLines()->exists();
        if ($hasTransactions) {
            throw new \InvalidArgumentException('Cannot delete an account with transaction history.');
        }

        $hasChildren = $account->children()->exists();
        if ($hasChildren) {
            throw new \InvalidArgumentException('Cannot delete an account that has sub-accounts.');
        }

        $account->delete();
    }

    public function getAccounts(int $organizationId, ?AccountType $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = ChartOfAccount::forOrganization($organizationId)->active();

        if ($type) {
            $query->where('account_type', $type);
        }

        return $query->orderBy('account_code')->get();
    }

    public function getAccount(int $organizationId, int $accountId): ChartOfAccount
    {
        $account = ChartOfAccount::where('id', $accountId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$account) {
            throw new \InvalidArgumentException('Account not found or does not belong to this organization.');
        }

        return $account;
    }

    public function initializeDefaultChart(int $organizationId, ?int $userId = null): void
    {
        DB::transaction(function () use ($organizationId, $userId) {
            $accounts = [
                ['1000', 'Assets', AccountType::Asset, null, true],
                ['1100', 'Cash on Hand', AccountType::Asset, '1000', true],
                ['1200', 'Bank Account', AccountType::Asset, '1000', true],
                ['1300', 'Mobile Money', AccountType::Asset, '1000', true],
                ['1400', 'Loans Receivable', AccountType::Asset, '1000', true],
                ['1500', 'Interest Receivable', AccountType::Asset, '1000', true],
                ['1600', 'Fees Receivable', AccountType::Asset, '1000', true],

                ['2000', 'Liabilities', AccountType::Liability, null, true],
                ['2100', 'Member Savings', AccountType::Liability, '2000', true],
                ['2200', 'Welfare Funds Payable', AccountType::Liability, '2000', true],
                ['2300', 'Share Capital', AccountType::Liability, '2000', true],

                ['3000', 'Equity', AccountType::Equity, null, true],
                ['3100', 'Retained Earnings', AccountType::Equity, '3000', true],
                ['3200', 'Opening Balance Equity', AccountType::Equity, '3000', true],

                ['4000', 'Income', AccountType::Income, null, true],
                ['4100', 'Loan Interest Income', AccountType::Income, '4000', true],
                ['4200', 'Loan Fees Income', AccountType::Income, '4000', true],
                ['4300', 'Other Financial Income', AccountType::Income, '4000', true],

                ['5000', 'Expenses', AccountType::Expense, null, true],
                ['5100', 'Operating Expenses', AccountType::Expense, '5000', true],
                ['5200', 'Other Financial Expenses', AccountType::Expense, '5000', true],
            ];

            foreach ($accounts as [$code, $name, $type, $parentCode, $isSystem]) {
                $parentId = null;
                if ($parentCode) {
                    $parent = ChartOfAccount::where('organization_id', $organizationId)
                        ->where('account_code', $parentCode)
                        ->first();
                    $parentId = $parent?->id;
                }

                $this->createAccount(
                    $organizationId,
                    $code,
                    $name,
                    $type,
                    null,
                    $isSystem,
                    $parentId,
                    $userId,
                );
            }
        });
    }
}
