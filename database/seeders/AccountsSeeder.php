<?php

namespace Database\Seeders;

use App\Enums\FinancialTransactionStatus;
use App\Enums\SavingsAccountStatus;
use App\Enums\SavingsTransactionType;
use App\Enums\ShareAccountStatus;
use App\Enums\ShareTransactionType;
use App\Enums\WelfareBenefitRequestStatus;
use App\Enums\WelfareTransactionType;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\ShareTransaction;
use App\Models\WelfareAccount;
use App\Models\WelfareBenefitRequest;
use App\Models\WelfareFund;
use App\Models\WelfareTransaction;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountsSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $this->accounts();
        $this->savingsTransactions();
        $this->shareTransactions();
        $this->welfareTransactions();
        $this->benefitRequests();

        $this->report('Accounts and transactions ready.');
    }

    private function accounts(): void
    {
        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $savingsProducts = SavingsProduct::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $shareProducts = ShareProduct::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $welfareFunds = WelfareFund::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();

        if (! $savingsProducts || ! $shareProducts || ! $welfareFunds) {
            return;
        }

        $sequence = SavingsAccount::where('organization_id', self::ORG_ID)->count();

        foreach ($members as $member) {
            if ($this->gap('savings_accounts') > 0 && ! SavingsAccount::where('member_id', $member->id)->exists()) {
                $sequence++;

                SavingsAccount::create([
                    'member_id' => $member->id,
                    'organization_id' => self::ORG_ID,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'savings_product_id' => $savingsProducts[$sequence % count($savingsProducts)],
                    'account_number' => sprintf('SA-FP-%06d', $sequence),
                    'opening_date' => $member->joining_date->toDateString(),
                    'status' => SavingsAccountStatus::Active,
                    'current_balance' => 0,
                    'created_by' => self::ACTOR_ID,
                ]);

                $this->bump('savings_accounts');
            }

            if ($this->gap('share_accounts') > 0 && ! ShareAccount::where('member_id', $member->id)->exists()) {
                $sequence = ShareAccount::where('organization_id', self::ORG_ID)->count() + 1;

                ShareAccount::create([
                    'member_id' => $member->id,
                    'organization_id' => self::ORG_ID,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'share_product_id' => $shareProducts[$sequence % count($shareProducts)],
                    'account_number' => sprintf('SH-FP-%06d', $sequence),
                    'total_shares' => 0,
                    'total_value' => 0,
                    'status' => ShareAccountStatus::Active,
                    'created_by' => self::ACTOR_ID,
                ]);

                $this->bump('share_accounts');
            }

            if ($this->gap('welfare_accounts') > 0 && ! WelfareAccount::where('member_id', $member->id)->exists()) {
                $sequence = WelfareAccount::where('organization_id', self::ORG_ID)->count() + 1;

                WelfareAccount::create([
                    'member_id' => $member->id,
                    'organization_id' => self::ORG_ID,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'welfare_fund_id' => $welfareFunds[$sequence % count($welfareFunds)],
                    'account_number' => sprintf('WA-FP-%06d', $sequence),
                    'current_balance' => 0,
                    'status' => 'active',
                    'created_by' => self::ACTOR_ID,
                ]);

                $this->bump('welfare_accounts');
            }
        }
    }

    private function savingsTransactions(): void
    {
        $accounts = SavingsAccount::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $methods = PaymentMethod::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $sequence = DB::table('savings_transactions')->count();

        foreach ($accounts as $account) {
            if ($this->gap('savings_transactions') <= 0) {
                break;
            }

            $balance = (float) $account->current_balance;

            for ($index = 0; $index < fake()->numberBetween(1, 3); $index++) {
                if ($this->gap('savings_transactions') <= 0) {
                    break;
                }

                $deposit = $index === 0 || $balance <= 0 || fake()->boolean(65);
                $amount = $deposit ? fake()->numberBetween(3, 120) * 10000 : min(fake()->numberBetween(2, 15) * 5000, (int) $balance);
                $date = $this->daysAgo(360, 5);
                $before = $balance;
                $balance = $deposit ? $before + $amount : max(0, $before - $amount);
                $sequence++;

                SavingsTransaction::create([
                    'savings_account_id' => $account->id,
                    'member_id' => $account->member_id,
                    'organization_id' => self::ORG_ID,
                    'branch_id' => $account->branch_id,
                    'vicoba_group_id' => $account->vicoba_group_id,
                    'transaction_number' => sprintf('SV-%s-%06d', $date->year, $sequence),
                    'transaction_type' => $deposit ? SavingsTransactionType::Deposit : SavingsTransactionType::Withdrawal,
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $balance,
                    'transaction_date' => $date->toDateString(),
                    'payment_method_id' => $this->pick($methods),
                    'reference' => 'RCPT'.fake()->numerify('########'),
                    'description' => $deposit ? 'Member cash deposit at the branch counter' : 'Member withdrawal request',
                    'status' => FinancialTransactionStatus::Completed,
                    'created_by' => self::ACTOR_ID,
                    'approved_by' => self::ACTOR_ID,
                    'approved_at' => $date->copy()->addMinutes(30),
                ]);

                $this->bump('savings_transactions');
            }

            $account->update(['current_balance' => $balance]);
        }
    }

    private function shareTransactions(): void
    {
        $accounts = ShareAccount::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $products = ShareProduct::where('organization_id', self::ORG_ID)->pluck('share_price', 'id');
        $methods = PaymentMethod::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $sequence = DB::table('share_transactions')->count();

        foreach ($accounts as $account) {
            if ($this->gap('share_transactions') <= 0) {
                break;
            }

            $shares = (int) $account->total_shares;
            $price = (float) ($products[$account->share_product_id] ?? 1000);

            for ($index = 0; $index < fake()->numberBetween(1, 3); $index++) {
                if ($this->gap('share_transactions') <= 0) {
                    break;
                }

                $purchase = $index === 0 || $shares < 10 || fake()->boolean(60);
                $quantity = $purchase ? fake()->numberBetween(2, 40) : min(fake()->numberBetween(2, 15), max(1, $shares - 5));
                $date = $this->daysAgo(350, 5);
                $before = $shares;
                $shares = $purchase ? $before + $quantity : max(0, $before - $quantity);
                $sequence++;

                ShareTransaction::create([
                    'share_account_id' => $account->id,
                    'member_id' => $account->member_id,
                    'organization_id' => self::ORG_ID,
                    'branch_id' => $account->branch_id,
                    'vicoba_group_id' => $account->vicoba_group_id,
                    'transaction_number' => sprintf('SH-%s-%06d', $date->year, $sequence),
                    'transaction_type' => $purchase ? ShareTransactionType::Purchase : ShareTransactionType::Redeem,
                    'quantity' => $quantity,
                    'share_price' => $price,
                    'amount' => round($quantity * $price, 2),
                    'balance_shares_before' => $before,
                    'balance_shares_after' => $shares,
                    'balance_value_before' => round($before * $price, 2),
                    'balance_value_after' => round($shares * $price, 2),
                    'transaction_date' => $date->toDateString(),
                    'payment_method_id' => $this->pick($methods),
                    'reference' => 'SHREF'.fake()->numerify('########'),
                    'description' => $purchase ? 'Share purchase recorded at the branch' : 'Share redemption request',
                    'status' => FinancialTransactionStatus::Completed,
                    'created_by' => self::ACTOR_ID,
                    'approved_by' => self::ACTOR_ID,
                    'approved_at' => $date->copy()->addMinutes(20),
                ]);

                $this->bump('share_transactions');
            }

            $account->update([
                'total_shares' => $shares,
                'total_value' => round($shares * $price, 2),
            ]);
        }
    }

    private function welfareTransactions(): void
    {
        $accounts = WelfareAccount::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $methods = PaymentMethod::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $sequence = DB::table('welfare_transactions')->count();

        foreach ($accounts as $account) {
            if ($this->gap('welfare_transactions') <= 0) {
                break;
            }

            $balance = (float) $account->current_balance;

            for ($index = 0; $index < fake()->numberBetween(1, 3); $index++) {
                if ($this->gap('welfare_transactions') <= 0) {
                    break;
                }

                $contribution = $index === 0 || $balance <= 0 || fake()->boolean(70);
                $amount = $contribution ? fake()->numberBetween(1, 8) * 10000 : min(fake()->numberBetween(1, 6) * 5000, (int) $balance);
                $date = $this->daysAgo(340, 5);
                $before = $balance;
                $balance = $contribution ? $before + $amount : max(0, $before - $amount);
                $sequence++;

                WelfareTransaction::create([
                    'welfare_account_id' => $account->id,
                    'member_id' => $account->member_id,
                    'organization_id' => self::ORG_ID,
                    'branch_id' => $account->branch_id,
                    'vicoba_group_id' => $account->vicoba_group_id,
                    'transaction_number' => sprintf('WF-%s-%06d', $date->year, $sequence),
                    'transaction_type' => $contribution ? WelfareTransactionType::Contribution : WelfareTransactionType::Benefit,
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $balance,
                    'transaction_date' => $date->toDateString(),
                    'payment_method_id' => $this->pick($methods),
                    'reference' => 'WFREF'.fake()->numerify('########'),
                    'description' => $contribution ? 'Monthly group welfare contribution' : 'Welfare benefit payment to the member',
                    'status' => FinancialTransactionStatus::Completed,
                    'created_by' => self::ACTOR_ID,
                    'approved_by' => self::ACTOR_ID,
                    'approved_at' => $date->copy()->addMinutes(45),
                ]);

                $this->bump('welfare_transactions');
            }

            $account->update(['current_balance' => $balance]);
        }
    }

    private function benefitRequests(): void
    {
        $accounts = WelfareAccount::where('organization_id', self::ORG_ID)->where('current_balance', '>', 0)->orderBy('id')->get();
        $statuses = [WelfareBenefitRequestStatus::Approved, WelfareBenefitRequestStatus::Pending, WelfareBenefitRequestStatus::Approved, WelfareBenefitRequestStatus::Rejected];
        $reasons = ['School fees for second term', 'Medical treatment expenses', 'Bicycle purchase for income generation', 'Bereavement support', 'Business restocking after low sales'];
        $methods = PaymentMethod::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $sequence = DB::table('welfare_benefit_requests')->count();

        foreach ($accounts as $index => $account) {
            if ($this->gap('welfare_benefit_requests', 25) <= 0) {
                break;
            }

            $status = $statuses[$index % count($statuses)];
            $approved = $status === WelfareBenefitRequestStatus::Approved;
            $rejected = $status === WelfareBenefitRequestStatus::Rejected;
            $date = $this->daysAgo(200, 3);
            $sequence++;

            $transaction = WelfareTransaction::where('welfare_account_id', $account->id)
                ->where('transaction_type', WelfareTransactionType::Benefit->value)
                ->inRandomOrder()
                ->first();

            WelfareBenefitRequest::create([
                'welfare_account_id' => $account->id,
                'member_id' => $account->member_id,
                'organization_id' => self::ORG_ID,
                'branch_id' => $account->branch_id,
                'vicoba_group_id' => $account->vicoba_group_id,
                'request_number' => sprintf('WBR-%s-%04d', $date->year, $sequence),
                'requested_amount' => min($this->money(10000, 200000), (float) $account->current_balance),
                'reason' => $reasons[$index % count($reasons)],
                'status' => $status,
                'payment_method_id' => $this->pick($methods),
                'approved_by' => $approved ? self::ACTOR_ID : null,
                'approved_at' => $approved ? $date->copy()->addDays(2) : null,
                'rejected_by' => $rejected ? self::ACTOR_ID : null,
                'rejected_at' => $rejected ? $date->copy()->addDays(2) : null,
                'rejection_reason' => $rejected ? 'Requested amount exceeds the available welfare balance.' : null,
                'welfare_transaction_id' => $approved ? $transaction?->id : null,
                'idempotency_key' => 'wbr-'.$date->year.'-'.str_pad($sequence, 6, '0', STR_PAD_LEFT),
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('welfare_benefit_requests');
        }
    }
}
