<?php

namespace App\Services;

use App\Models\SavingsTransaction;
use App\Models\SavingsAccount;
use App\Models\LoanRepayment;
use App\Models\LoanDisbursement;
use App\Models\ShareTransaction;
use App\Models\WelfareTransaction;

class AccountingEventService
{
    public function __construct(
        protected JournalPostingService $postingService,
        protected JournalReversalService $reversalService,
        protected AccountingConfigurationService $configService,
        protected AuditService $auditService,
    ) {}

    public function recordSavingsDeposit(SavingsTransaction $transaction): void
    {
        if ($this->postingService->hasPostedJournal(SavingsTransaction::class, $transaction->id)) {
            return;
        }

        $orgId = $transaction->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $transaction->payment_method_id ?? null);
        $savingsAccountId = $this->configService->getAccountId($orgId, 'member_savings');

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $transaction->branch_id,
            'entry_date' => $transaction->transaction_date->format('Y-m-d'),
            'description' => "Savings deposit - {$transaction->transaction_number}",
            'source_type' => SavingsTransaction::class,
            'source_id' => $transaction->id,
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => $transaction->amount, 'credit' => 0],
                ['chart_of_account_id' => $savingsAccountId, 'debit' => 0, 'credit' => $transaction->amount],
            ],
        ], $transaction->created_by);
    }

    public function recordSavingsWithdrawal(SavingsTransaction $transaction): void
    {
        if ($this->postingService->hasPostedJournal(SavingsTransaction::class, $transaction->id)) {
            return;
        }

        $orgId = $transaction->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $transaction->payment_method_id ?? null);
        $savingsAccountId = $this->configService->getAccountId($orgId, 'member_savings');

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $transaction->branch_id,
            'entry_date' => $transaction->transaction_date->format('Y-m-d'),
            'description' => "Savings withdrawal - {$transaction->transaction_number}",
            'source_type' => SavingsTransaction::class,
            'source_id' => $transaction->id,
            'lines' => [
                ['chart_of_account_id' => $savingsAccountId, 'debit' => $transaction->amount, 'credit' => 0],
                ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => $transaction->amount],
            ],
        ], $transaction->created_by);
    }

    public function recordSharePurchase(ShareTransaction $transaction): void
    {
        if ($this->postingService->hasPostedJournal(ShareTransaction::class, $transaction->id)) {
            return;
        }

        $orgId = $transaction->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $transaction->payment_method_id ?? null);
        $shareCapitalId = $this->configService->getAccountId($orgId, 'share_capital');

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $transaction->branch_id,
            'entry_date' => $transaction->transaction_date->format('Y-m-d'),
            'description' => "Share purchase - {$transaction->transaction_number}",
            'source_type' => ShareTransaction::class,
            'source_id' => $transaction->id,
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => $transaction->amount, 'credit' => 0],
                ['chart_of_account_id' => $shareCapitalId, 'debit' => 0, 'credit' => $transaction->amount],
            ],
        ], $transaction->created_by);
    }

    public function recordShareRedemption(ShareTransaction $transaction): void
    {
        if ($this->postingService->hasPostedJournal(ShareTransaction::class, $transaction->id)) {
            return;
        }

        $orgId = $transaction->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $transaction->payment_method_id ?? null);
        $shareCapitalId = $this->configService->getAccountId($orgId, 'share_capital');

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $transaction->branch_id,
            'entry_date' => $transaction->transaction_date->format('Y-m-d'),
            'description' => "Share redemption - {$transaction->transaction_number}",
            'source_type' => ShareTransaction::class,
            'source_id' => $transaction->id,
            'lines' => [
                ['chart_of_account_id' => $shareCapitalId, 'debit' => $transaction->amount, 'credit' => 0],
                ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => $transaction->amount],
            ],
        ], $transaction->created_by);
    }

    public function recordWelfareContribution(WelfareTransaction $transaction): void
    {
        if ($this->postingService->hasPostedJournal(WelfareTransaction::class, $transaction->id)) {
            return;
        }

        $orgId = $transaction->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $transaction->payment_method_id ?? null);
        $welfarePayableId = $this->configService->getAccountId($orgId, 'welfare_funds_payable');

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $transaction->branch_id,
            'entry_date' => $transaction->transaction_date->format('Y-m-d'),
            'description' => "Welfare contribution - {$transaction->transaction_number}",
            'source_type' => WelfareTransaction::class,
            'source_id' => $transaction->id,
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => $transaction->amount, 'credit' => 0],
                ['chart_of_account_id' => $welfarePayableId, 'debit' => 0, 'credit' => $transaction->amount],
            ],
        ], $transaction->created_by);
    }

    public function recordWelfareBenefit(WelfareTransaction $transaction): void
    {
        if ($this->postingService->hasPostedJournal(WelfareTransaction::class, $transaction->id)) {
            return;
        }

        $orgId = $transaction->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $transaction->payment_method_id ?? null);
        $welfarePayableId = $this->configService->getAccountId($orgId, 'welfare_funds_payable');

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $transaction->branch_id,
            'entry_date' => $transaction->transaction_date->format('Y-m-d'),
            'description' => "Welfare benefit - {$transaction->transaction_number}",
            'source_type' => WelfareTransaction::class,
            'source_id' => $transaction->id,
            'lines' => [
                ['chart_of_account_id' => $welfarePayableId, 'debit' => $transaction->amount, 'credit' => 0],
                ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => $transaction->amount],
            ],
        ], $transaction->created_by);
    }

    public function recordLoanDisbursement(LoanDisbursement $disbursement): void
    {
        if ($this->postingService->hasPostedJournal(LoanDisbursement::class, $disbursement->id)) {
            return;
        }

        $orgId = $disbursement->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $disbursement->payment_method_id ?? null);
        $loanReceivableId = $this->configService->getAccountId($orgId, 'loans_receivable');
        $amount = (float) $disbursement->loan->principal_amount;

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $disbursement->branch_id,
            'entry_date' => $disbursement->disbursement_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'description' => "Loan disbursement - {$disbursement->disbursement_number}",
            'source_type' => LoanDisbursement::class,
            'source_id' => $disbursement->id,
            'lines' => [
                ['chart_of_account_id' => $loanReceivableId, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => $amount],
            ],
        ], $disbursement->processed_by);
    }

    public function recordLoanRepayment(LoanRepayment $repayment): void
    {
        if ($this->postingService->hasPostedJournal(LoanRepayment::class, $repayment->id)) {
            return;
        }

        $orgId = $repayment->organization_id;
        $cashAccountId = $this->resolveCashAccount($orgId, $repayment->payment_method_id ?? null);
        $loanReceivableId = $this->configService->getAccountId($orgId, 'loans_receivable');
        $interestIncomeId = $this->configService->getAccountId($orgId, 'loan_interest_income');
        $feeIncomeId = $this->configService->getAccountId($orgId, 'loan_fees_income');

        $lines = [];

        $lines[] = ['chart_of_account_id' => $cashAccountId, 'debit' => (float) $repayment->amount, 'credit' => 0];

        if ((float) $repayment->principal_portion > 0) {
            $lines[] = ['chart_of_account_id' => $loanReceivableId, 'debit' => 0, 'credit' => (float) $repayment->principal_portion];
        }
        if ((float) $repayment->interest_portion > 0) {
            $lines[] = ['chart_of_account_id' => $interestIncomeId, 'debit' => 0, 'credit' => (float) $repayment->interest_portion];
        }
        if ((float) $repayment->fee_portion > 0) {
            $lines[] = ['chart_of_account_id' => $feeIncomeId, 'debit' => 0, 'credit' => (float) $repayment->fee_portion];
        }

        $overpayment = (float) ($repayment->overpayment_amount ?? 0);
        if ($overpayment > 0) {
            $savingsAccountId = $this->configService->getAccountId($orgId, 'member_savings');
            $lines[] = ['chart_of_account_id' => $savingsAccountId, 'debit' => 0, 'credit' => $overpayment];
        }

        $this->postingService->createAndPost([
            'organization_id' => $orgId,
            'branch_id' => $repayment->branch_id,
            'entry_date' => $repayment->payment_date->format('Y-m-d'),
            'description' => "Loan repayment - {$repayment->repayment_number}",
            'source_type' => LoanRepayment::class,
            'source_id' => $repayment->id,
            'lines' => $lines,
        ], $repayment->received_by);
    }

    public function recordRepaymentReversal(LoanRepayment $repayment): void
    {
        $this->reverseSourceJournal(
            LoanRepayment::class,
            $repayment->id,
            $repayment->reversal_reason ?? 'Repayment reversal',
            $repayment->reversed_by,
        );
    }

    public function reverseSourceJournal(string $sourceType, int $sourceId, string $reason, ?int $userId): void
    {
        $originalJournal = $this->postingService->getSourceJournal($sourceType, $sourceId);

        if (!$originalJournal) {
            return;
        }

        $this->reversalService->reverseJournal(
            $originalJournal,
            $reason,
            $userId,
        );
    }

    protected function resolveCashAccount(int $organizationId, ?int $paymentMethodId): int
    {
        if ($paymentMethodId) {
            $pm = \App\Models\PaymentMethod::where('id', $paymentMethodId)
                ->where('organization_id', $organizationId)
                ->first();

            if ($pm) {
                $type = $pm->type;
                if ($type && $type->value === 'bank') {
                    return $this->configService->getAccountId($organizationId, 'bank_account');
                }
                if ($type && $type->value === 'mobile_money') {
                    return $this->configService->getAccountId($organizationId, 'mobile_money');
                }
            }
        }

        return $this->configService->getAccountId($organizationId, 'cash_on_hand');
    }
}
