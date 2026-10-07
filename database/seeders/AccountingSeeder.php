<?php

namespace Database\Seeders;

use App\Enums\JournalEntryStatus;
use App\Enums\SavingsTransactionType;
use App\Enums\WelfareTransactionType;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LoanDisbursement;
use App\Models\LoanRepayment;
use App\Models\PaymentMethod;
use App\Models\SavingsTransaction;
use App\Models\WelfareTransaction;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $this->mappings();
        $this->journalEntries();

        $this->report('Accounting ledger ready.');
    }

    private function mappings(): void
    {
        $accounts = ChartOfAccount::where('organization_id', self::ORG_ID)->pluck('id', 'account_code');

        $keys = [
            'cash_account' => '1100',
            'bank_account' => '1200',
            'mobile_money_account' => '1300',
            'loans_receivable' => '1400',
            'interest_receivable' => '1500',
            'fees_receivable' => '1600',
            'share_capital' => '2300',
            'interest_income' => '4100',
            'operating_expenses' => '5100',
        ];

        foreach ($keys as $key => $code) {
            $accountId = $accounts[$code] ?? null;

            if (! $accountId) {
                continue;
            }

            if (DB::table('account_mappings')->where('organization_id', self::ORG_ID)->where('mapping_key', $key)->exists()) {
                continue;
            }

            DB::table('account_mappings')->insert([
                'organization_id' => self::ORG_ID,
                'mapping_key' => $key,
                'chart_of_account_id' => $accountId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->bump('account_mappings');
        }
    }

    private function journalEntries(): void
    {
        $accounts = ChartOfAccount::where('organization_id', self::ORG_ID)->pluck('id', 'account_code');
        $periods = AccountingPeriod::where('organization_id', self::ORG_ID)->pluck('id', 'name');
        $methods = PaymentMethod::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();

        $cash = $accounts['1100'] ?? null;
        $bank = $accounts['1200'] ?? null;
        $mobile = $accounts['1300'] ?? null;
        $savingsPayable = $accounts['2100'] ?? null;
        $welfarePayable = $accounts['2200'] ?? null;
        $shareCapital = $accounts['2300'] ?? null;
        $interestIncome = $accounts['4100'] ?? null;
        $expenses = $accounts['5100'] ?? null;

        $cashAccounts = array_values(array_filter([$cash, $bank, $mobile]));

        if (! $cashAccounts || ! $savingsPayable || ! $interestIncome || ! $expenses) {
            return;
        }

        $sequence = DB::table('journal_entries')->count();
        $sources = [];

        $savings = SavingsTransaction::where('organization_id', self::ORG_ID)->orderBy('id')->limit(60)->get();

        foreach ($savings as $transaction) {
            $sources[] = [
                'date' => $transaction->transaction_date,
                'amount' => (float) $transaction->amount,
                'isCredit' => $transaction->transaction_type === SavingsTransactionType::Deposit,
                'description' => 'Savings '.$transaction->transaction_type->value.' for member '.$transaction->member_id,
                'creditAccount' => $savingsPayable,
                'debitAccount' => $cashAccounts[count($sources) % count($cashAccounts)],
                'source' => $transaction,
            ];
        }

        $welfare = WelfareTransaction::where('organization_id', self::ORG_ID)->orderBy('id')->limit(40)->get();

        foreach ($welfare as $transaction) {
            $sources[] = [
                'date' => $transaction->transaction_date,
                'amount' => (float) $transaction->amount,
                'isCredit' => $transaction->transaction_type === WelfareTransactionType::Contribution,
                'description' => 'Welfare '.$transaction->transaction_type->value.' for member '.$transaction->member_id,
                'creditAccount' => $welfarePayable ?? $savingsPayable,
                'debitAccount' => $cashAccounts[count($sources) % count($cashAccounts)],
                'source' => $transaction,
            ];
        }

        $disbursements = LoanDisbursement::where('organization_id', self::ORG_ID)
            ->where('status', 'confirmed')
            ->orderBy('id')
            ->limit(30)
            ->get();

        foreach ($disbursements as $disbursement) {
            $sources[] = [
                'date' => $disbursement->disbursement_date,
                'amount' => (float) $disbursement->amount,
                'isCredit' => false,
                'description' => 'Loan disbursement '.$disbursement->disbursement_number,
                'creditAccount' => $accounts['1400'] ?? $savingsPayable,
                'debitAccount' => $cashAccounts[count($sources) % count($cashAccounts)],
                'source' => $disbursement,
            ];
        }

        $repayments = LoanRepayment::where('organization_id', self::ORG_ID)
            ->where('status', 'posted')
            ->orderBy('id')
            ->limit(40)
            ->get();

        foreach ($repayments as $repayment) {
            $sources[] = [
                'date' => $repayment->payment_date,
                'amount' => (float) $repayment->amount,
                'isCredit' => true,
                'description' => 'Loan repayment '.$repayment->repayment_number,
                'creditAccount' => $interestIncome,
                'debitAccount' => $cashAccounts[count($sources) % count($cashAccounts)],
                'source' => $repayment,
            ];
        }

        foreach ($sources as $index => $source) {
            if ($this->gap('journal_entries', 60) <= 0) {
                break;
            }

            $date = Carbon::parse($source['date']);
            $periodId = $this->periodFor($date, $periods);
            $posted = $date->isPast();
            $sequence++;

            $entry = JournalEntry::create([
                'organization_id' => self::ORG_ID,
                'branch_id' => $source['source']->branch_id ?? null,
                'accounting_period_id' => $periodId,
                'journal_number' => sprintf('JRN-%s-%05d', $date->year, $sequence),
                'entry_date' => $date->toDateString(),
                'description' => $source['description'],
                'reference_type' => class_basename($source['source']),
                'reference_id' => $source['source']->id,
                'source_type' => $source['source'] instanceof Model ? $source['source']::class : null,
                'source_id' => $source['source']->id ?? null,
                'status' => $posted ? JournalEntryStatus::Posted : JournalEntryStatus::Draft,
                'posted_at' => $posted ? $date->copy()->addHours(18) : null,
                'posted_by' => $posted ? self::ACTOR_ID : null,
                'created_by' => self::ACTOR_ID,
            ]);

            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'organization_id' => self::ORG_ID,
                'chart_of_account_id' => $source['isCredit'] ? $source['creditAccount'] : $source['debitAccount'],
                'description' => $source['description'],
                'debit' => $source['isCredit'] ? 0 : $source['amount'],
                'credit' => $source['isCredit'] ? $source['amount'] : 0,
            ]);

            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'organization_id' => self::ORG_ID,
                'chart_of_account_id' => $source['isCredit'] ? $source['debitAccount'] : $source['creditAccount'],
                'description' => $source['description'],
                'debit' => $source['isCredit'] ? $source['amount'] : 0,
                'credit' => $source['isCredit'] ? 0 : $source['amount'],
            ]);

            $this->bump('journal_entries');
            $this->bump('journal_lines', 2);
        }
    }

    private function periodFor(Carbon $date, $periods): ?int
    {
        foreach ($periods as $name => $id) {
            if (str_contains($name, (string) $date->year)) {
                return (int) $id;
            }
        }

        return $periods->first();
    }
}
