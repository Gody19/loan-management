<?php

namespace Database\Seeders;

use App\Enums\FinancialTransactionStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanStatus;
use App\Enums\SavingsAccountStatus;
use App\Enums\ShareAccountStatus;
use App\Enums\WelfareAccountStatus;
use App\Enums\WelfareBenefitRequestStatus;
use App\Enums\WelfareTransactionType;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApprovalLevel;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareBenefitRequest;
use App\Models\WelfareFund;
use App\Models\WelfareTransaction;
use App\Services\TransactionNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinancialSeeder extends Seeder
{
    private TransactionNumberGenerator $numberGenerator;
    private int $savingsTxCounter = 0;
    private int $shareTxCounter = 0;
    private int $welfareTxCounter = 0;
    private int $wbrCounter = 0;
    private int $loanCounter = 0;
    private int $repaymentCounter = 0;
    private int $scheduleCounter = 0;

    public function run(): void
    {
        $this->numberGenerator = new TransactionNumberGenerator;

        $organizations = Organization::all();
        $members = Member::active()->get();
        $admin = User::where('email', 'admin@financepro.co.tz')->first()
            ?? User::first();

        if ($organizations->isEmpty() || $members->isEmpty()) {
            $this->command->warn('Please run Organization, Branch, Group, and Member seeders first.');

            return;
        }

        $this->command->info('Seeding payment methods...');
        $paymentMethods = $this->seedPaymentMethods($organizations, $admin);

        $this->command->info('Seeding savings products...');
        $savingsProducts = $this->seedSavingsProducts($organizations, $admin);

        $this->command->info('Seeding share products...');
        $shareProducts = $this->seedShareProducts($organizations, $admin);

        $this->command->info('Seeding welfare funds...');
        $welfareFunds = $this->seedWelfareFunds($organizations, $admin);

        $this->command->info('Seeding loan plans...');
        $loanPlans = $this->seedLoanPlans($organizations, $admin);

        $this->command->info('Seeding loan approval levels...');
        $this->seedLoanApprovalLevels($organizations, $admin);

        $this->command->info('Seeding savings accounts and transactions...');
        $savingsAccounts = $this->seedSavingsAccounts($members, $savingsProducts, $paymentMethods, $admin);

        $this->command->info('Seeding share accounts and transactions...');
        $shareAccounts = $this->seedShareAccounts($members, $shareProducts, $paymentMethods, $admin);

        $this->command->info('Seeding welfare accounts and transactions...');
        $this->seedWelfareAccounts($members, $welfareFunds, $paymentMethods, $admin);

        $this->command->info('Seeding loan applications and active loans...');
        $this->seedLoans($members, $loanPlans, $paymentMethods, $admin);

        $this->command->info('Financial seeding complete.');
    }

    // ==================== Payment Methods ====================

    private function seedPaymentMethods($organizations, User $admin): \Illuminate\Support\Collection
    {
        $methods = collect();
        $types = [
            ['name' => 'Cash', 'code' => 'CASH', 'type' => 'cash'],
            ['name' => 'Mobile Money (M-Pesa)', 'code' => 'MPESA', 'type' => 'mobile_money'],
            ['name' => 'Bank Transfer', 'code' => 'BANK', 'type' => 'bank'],
        ];

        foreach ($organizations as $org) {
            foreach ($types as $type) {
                $methods->push(PaymentMethod::create([
                    'organization_id' => $org->id,
                    'name' => $type['name'],
                    'code' => $type['code'],
                    'type' => $type['type'],
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]));
            }
        }

        $this->command->info("  Created {$methods->count()} payment methods.");

        return $methods;
    }

    // ==================== Savings Products ====================

    private function seedSavingsProducts($organizations, User $admin): \Illuminate\Support\Collection
    {
        $products = collect();
        $defs = [
            ['name' => 'Regular Savings', 'code' => 'REG-SAV', 'min' => 5000, 'max' => 5000000, 'min_bal' => 0, 'withdrawal' => true, 'limit' => 3],
            ['name' => 'Fixed Deposit', 'code' => 'FIX-DEP', 'min' => 100000, 'max' => 50000000, 'min_bal' => 50000, 'withdrawal' => false, 'limit' => 0],
        ];

        foreach ($organizations as $org) {
            foreach ($defs as $def) {
                $products->push(SavingsProduct::create([
                    'organization_id' => $org->id,
                    'name' => $def['name'],
                    'code' => $def['code'],
                    'description' => "{$def['name']} product for {$org->name}",
                    'minimum_amount' => $def['min'],
                    'maximum_amount' => $def['max'],
                    'minimum_balance' => $def['min_bal'],
                    'allow_withdrawal' => $def['withdrawal'],
                    'withdrawal_limit' => $def['limit'],
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]));
            }
        }

        $this->command->info("  Created {$products->count()} savings products.");

        return $products;
    }

    // ==================== Share Products ====================

    private function seedShareProducts($organizations, User $admin): \Illuminate\Support\Collection
    {
        $products = collect();

        foreach ($organizations as $org) {
            $products->push(ShareProduct::create([
                'organization_id' => $org->id,
                'name' => 'Standard Shares',
                'code' => 'STD-SHR',
                'description' => "Standard share product for {$org->name}",
                'share_price' => 10000,
                'minimum_shares' => 1,
                'maximum_shares' => 1000,
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]));
        }

        $this->command->info("  Created {$products->count()} share products.");

        return $products;
    }

    // ==================== Welfare Funds ====================

    private function seedWelfareFunds($organizations, User $admin): \Illuminate\Support\Collection
    {
        $funds = collect();
        $defs = [
            ['name' => 'Welfare Fund', 'code' => 'WLF', 'type' => 'fixed', 'amount' => 20000],
            ['name' => 'Emergency Fund', 'code' => 'EMR', 'type' => 'variable', 'amount' => 50000],
        ];

        foreach ($organizations as $org) {
            foreach ($defs as $def) {
                $funds->push(WelfareFund::create([
                    'organization_id' => $org->id,
                    'name' => $def['name'],
                    'code' => $def['code'],
                    'description' => "{$def['name']} for {$org->name}",
                    'contribution_type' => $def['type'],
                    'default_amount' => $def['amount'],
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]));
            }
        }

        $this->command->info("  Created {$funds->count()} welfare funds.");

        return $funds;
    }

    // ==================== Loan Plans ====================

    private function seedLoanPlans($organizations, User $admin): \Illuminate\Support\Collection
    {
        $plans = collect();
        $defs = [
            [
                'name' => 'Standard Loan', 'code' => 'STD-LOAN', 'purpose' => 'personal',
                'min' => 50000, 'max' => 5000000, 'rate' => 10, 'method' => 'flat',
                'min_term' => 3, 'max_term' => 24, 'freq' => 'monthly',
                'max_active' => 2, 'guarantor' => true, 'min_guarantors' => 1,
                'collateral' => false, 'min_savings' => 100000, 'savings_mult' => 2,
                'share_mult' => 1, 'max_ratio' => 3, 'grace' => 5,
                'processing' => 1, 'insurance' => 0.5, 'late_allowed' => true,
            ],
            [
                'name' => 'Emergency Loan', 'code' => 'EMR-LOAN', 'purpose' => 'emergency',
                'min' => 10000, 'max' => 500000, 'rate' => 5, 'method' => 'flat',
                'min_term' => 1, 'max_term' => 6, 'freq' => 'monthly',
                'max_active' => 1, 'guarantor' => false, 'min_guarantors' => 0,
                'collateral' => false, 'min_savings' => 20000, 'savings_mult' => 1,
                'share_mult' => 0, 'max_ratio' => 2, 'grace' => 0,
                'processing' => 0, 'insurance' => 0, 'late_allowed' => false,
            ],
        ];

        foreach ($organizations as $org) {
            foreach ($defs as $def) {
                $plans->push(LoanPlan::create([
                    'organization_id' => $org->id,
                    'name' => $def['name'],
                    'code' => $def['code'],
                    'description' => "{$def['name']} plan for {$org->name}",
                    'loan_purpose' => $def['purpose'],
                    'minimum_amount' => $def['min'],
                    'maximum_amount' => $def['max'],
                    'interest_rate' => $def['rate'],
                    'interest_method' => $def['method'],
                    'minimum_term' => $def['min_term'],
                    'maximum_term' => $def['max_term'],
                    'repayment_frequency' => $def['freq'],
                    'maximum_active_loans' => $def['max_active'],
                    'requires_guarantor' => $def['guarantor'],
                    'minimum_guarantors' => $def['min_guarantors'],
                    'requires_collateral' => $def['collateral'],
                    'minimum_savings_balance' => $def['min_savings'],
                    'savings_multiplier' => $def['savings_mult'],
                    'share_multiplier' => $def['share_mult'],
                    'maximum_loan_to_savings_ratio' => $def['max_ratio'],
                    'grace_period' => $def['grace'],
                    'processing_fee' => $def['processing'],
                    'insurance_fee' => $def['insurance'],
                    'late_payment_allowed' => $def['late_allowed'],
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]));
            }
        }

        $this->command->info("  Created {$plans->count()} loan plans.");

        return $plans;
    }

    // ==================== Loan Approval Levels ====================

    private function seedLoanApprovalLevels($organizations, User $admin): void
    {
        $count = 0;

        foreach ($organizations as $org) {
            LoanApprovalLevel::create([
                'organization_id' => $org->id,
                'name' => 'Standard Approval',
                'minimum_amount' => 0,
                'maximum_amount' => 10000000,
                'level' => 1,
                'required_permission' => 'loan.approve',
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $count++;
        }

        $this->command->info("  Created {$count} loan approval levels.");
    }

    // ==================== Savings Accounts + Transactions ====================

    private function seedSavingsAccounts($members, $savingsProducts, $paymentMethods, User $admin): \Illuminate\Support\Collection
    {
        $accounts = collect();
        $savingsAccountNumber = 1;
        $count = 0;

        foreach ($members as $member) {
            $product = $savingsProducts->where('organization_id', $member->organization_id)->first();
            if (! $product) {
                continue;
            }

            $openingBalance = fake()->randomFloat(2, 10000, 500000);
            $account = SavingsAccount::create([
                'member_id' => $member->id,
                'organization_id' => $member->organization_id,
                'branch_id' => $member->branch_id,
                'vicoba_group_id' => $member->vicoba_group_id,
                'savings_product_id' => $product->id,
                'account_number' => 'SAV-'.str_pad($savingsAccountNumber++, 6, '0', STR_PAD_LEFT),
                'opening_date' => $member->joining_date,
                'status' => SavingsAccountStatus::Active,
                'current_balance' => $openingBalance,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $accounts->push($account);
            $count++;

            $pm = $paymentMethods->where('organization_id', $member->organization_id)->random();
            $balance = $openingBalance;

            $numDeposits = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $numDeposits; $i++) {
                $amount = fake()->randomFloat(2, 5000, 200000);
                $balanceBefore = $balance;
                $balance += $amount;

                SavingsTransaction::create([
                    'savings_account_id' => $account->id,
                    'member_id' => $member->id,
                    'organization_id' => $member->organization_id,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'transaction_number' => $this->numberGenerator->generate('SVT'),
                    'transaction_type' => 'deposit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balance,
                    'transaction_date' => fake()->dateTimeBetween($member->joining_date, 'now'),
                    'payment_method_id' => $pm->id,
                    'reference' => fake()->optional(0.5)->bothify('REF-####-####'),
                    'description' => 'Savings deposit',
                    'status' => FinancialTransactionStatus::Completed,
                    'created_by' => $admin->id,
                ]);
            }

            $account->update(['current_balance' => $balance]);
        }

        $this->command->info("  Created {$count} savings accounts with transactions.");

        return $accounts;
    }

    // ==================== Share Accounts + Transactions ====================

    private function seedShareAccounts($members, $shareProducts, $paymentMethods, User $admin): \Illuminate\Support\Collection
    {
        $accounts = collect();
        $shareAccountNumber = 1;
        $count = 0;

        foreach ($members as $member) {
            if (! fake()->boolean(50)) {
                continue;
            }

            $product = $shareProducts->where('organization_id', $member->organization_id)->first();
            if (! $product) {
                continue;
            }

            $quantity = fake()->numberBetween(5, 50);
            $totalValue = $quantity * (float) $product->share_price;

            $account = ShareAccount::create([
                'member_id' => $member->id,
                'organization_id' => $member->organization_id,
                'branch_id' => $member->branch_id,
                'vicoba_group_id' => $member->vicoba_group_id,
                'share_product_id' => $product->id,
                'account_number' => 'SHR-'.str_pad($shareAccountNumber++, 6, '0', STR_PAD_LEFT),
                'total_shares' => $quantity,
                'total_value' => $totalValue,
                'status' => ShareAccountStatus::Active,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $accounts->push($account);
            $count++;

            $pm = $paymentMethods->where('organization_id', $member->organization_id)->random();

            ShareTransaction::create([
                'share_account_id' => $account->id,
                'member_id' => $member->id,
                'organization_id' => $member->organization_id,
                'branch_id' => $member->branch_id,
                'vicoba_group_id' => $member->vicoba_group_id,
                'transaction_number' => $this->numberGenerator->generate('SHT'),
                'transaction_type' => 'purchase',
                'quantity' => $quantity,
                'share_price' => $product->share_price,
                'amount' => $totalValue,
                'balance_shares_before' => 0,
                'balance_shares_after' => $quantity,
                'balance_value_before' => 0,
                'balance_value_after' => $totalValue,
                'transaction_date' => fake()->dateTimeBetween($member->joining_date, 'now'),
                'payment_method_id' => $pm->id,
                'reference' => fake()->optional(0.5)->bothify('REF-####-####'),
                'description' => 'Share purchase',
                'status' => FinancialTransactionStatus::Completed,
                'created_by' => $admin->id,
            ]);
        }

        $this->command->info("  Created {$count} share accounts with transactions.");

        return $accounts;
    }

    // ==================== Welfare Accounts + Transactions ====================

    private function seedWelfareAccounts($members, $welfareFunds, $paymentMethods, User $admin): void
    {
        $count = 0;

        foreach ($members as $member) {
            $fund = $welfareFunds->where('organization_id', $member->organization_id)->first();
            if (! $fund) {
                continue;
            }

            $balance = fake()->randomFloat(2, 20000, 300000);

            $account = WelfareAccount::create([
                'member_id' => $member->id,
                'organization_id' => $member->organization_id,
                'branch_id' => $member->branch_id,
                'vicoba_group_id' => $member->vicoba_group_id,
                'welfare_fund_id' => $fund->id,
                'account_number' => WelfareAccount::generateAccountNumber(),
                'current_balance' => $balance,
                'status' => WelfareAccountStatus::Active,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $count++;
            $pm = $paymentMethods->where('organization_id', $member->organization_id)->random();

            $numContributions = fake()->numberBetween(1, 2);
            $runningBalance = 0;

            for ($i = 0; $i < $numContributions; $i++) {
                $amount = fake()->randomFloat(2, 10000, 100000);
                $balanceBefore = $runningBalance;
                $runningBalance += $amount;

                WelfareTransaction::create([
                    'welfare_account_id' => $account->id,
                    'member_id' => $member->id,
                    'organization_id' => $member->organization_id,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'transaction_number' => $this->numberGenerator->generate('WFT'),
                    'transaction_type' => WelfareTransactionType::Contribution,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $runningBalance,
                    'transaction_date' => fake()->dateTimeBetween($member->joining_date, 'now'),
                    'payment_method_id' => $pm->id,
                    'reference' => fake()->optional(0.5)->bothify('REF-####-####'),
                    'description' => 'Welfare contribution',
                    'status' => FinancialTransactionStatus::Completed,
                    'created_by' => $admin->id,
                ]);
            }

            $account->update(['current_balance' => $runningBalance]);

            if (fake()->boolean(25) && $runningBalance > 20000) {
                $benefitAmount = fake()->randomFloat(2, 10000, min(50000, $runningBalance - 5000));
                $balanceBefore = $runningBalance;
                $runningBalance -= $benefitAmount;

                WelfareTransaction::create([
                    'welfare_account_id' => $account->id,
                    'member_id' => $member->id,
                    'organization_id' => $member->organization_id,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'transaction_number' => $this->numberGenerator->generate('WFT'),
                    'transaction_type' => WelfareTransactionType::Benefit,
                    'amount' => $benefitAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $runningBalance,
                    'transaction_date' => fake()->dateTimeBetween($member->joining_date, 'now'),
                    'payment_method_id' => $pm->id,
                    'reference' => fake()->optional(0.5)->bothify('REF-####-####'),
                    'description' => 'Welfare benefit payout',
                    'status' => FinancialTransactionStatus::Completed,
                    'created_by' => $admin->id,
                ]);

                $account->update(['current_balance' => $runningBalance]);

                WelfareBenefitRequest::create([
                    'welfare_account_id' => $account->id,
                    'member_id' => $member->id,
                    'organization_id' => $member->organization_id,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'request_number' => $this->numberGenerator->generate('WBR'),
                    'requested_amount' => $benefitAmount,
                    'reason' => fake()->randomElement(['Medical emergency', 'School fees', 'Family support', 'House repair']),
                    'status' => WelfareBenefitRequestStatus::Approved,
                    'payment_method_id' => $pm->id,
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDays(fake()->numberBetween(1, 30)),
                    'created_by' => $member->user_id,
                ]);
            }

            if (fake()->boolean(15)) {
                WelfareBenefitRequest::create([
                    'welfare_account_id' => $account->id,
                    'member_id' => $member->id,
                    'organization_id' => $member->organization_id,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'request_number' => $this->numberGenerator->generate('WBR'),
                    'requested_amount' => fake()->randomFloat(2, 10000, 50000),
                    'reason' => fake()->randomElement(['Medical bills', 'Education', 'Travel expenses']),
                    'status' => WelfareBenefitRequestStatus::Rejected,
                    'rejected_by' => $admin->id,
                    'rejected_at' => now()->subDays(fake()->numberBetween(1, 15)),
                    'rejection_reason' => fake()->randomElement(['Insufficient balance', 'Does not meet criteria', 'Pending review']),
                    'created_by' => $member->user_id,
                ]);
            }
        }

        $this->command->info("  Created {$count} welfare accounts with transactions.");
    }

    // ==================== Loans ====================

    private function seedLoans($members, $loanPlans, $paymentMethods, User $admin): void
    {
        $loanCount = 0;
        $repaymentCount = 0;
        $appNumber = 1;

        $activeMembers = $members->filter(fn ($m) => $m->membership_status->value === 'active')->take(25);

        foreach ($activeMembers as $member) {
            if (! fake()->boolean(35)) {
                continue;
            }

            $plan = $loanPlans->where('organization_id', $member->organization_id)->first();
            if (! $plan) {
                continue;
            }

            $amount = fake()->randomFloat(2, (float) $plan->minimum_amount, min((float) $plan->maximum_amount, 2000000));
            $term = fake()->numberBetween($plan->minimum_term, min($plan->maximum_term, 12));
            $interestRate = (float) $plan->interest_rate;
            $totalInterest = $amount * ($interestRate / 100) * ($term / 12);
            $totalAmount = $amount + $totalInterest;
            $monthlyPayment = $totalAmount / $term;

            $disbursementDate = fake()->dateTimeBetween('-6 months', '-1 month');
            $maturityDate = (clone $disbursementDate)->modify("+{$term} months");
            $applicationDate = (clone $disbursementDate)->modify('-2 weeks');

            $application = LoanApplication::create([
                'organization_id' => $member->organization_id,
                'branch_id' => $member->branch_id,
                'vicoba_group_id' => $member->vicoba_group_id,
                'member_id' => $member->id,
                'loan_plan_id' => $plan->id,
                'application_number' => 'LA-'.str_pad($appNumber++, 6, '0', STR_PAD_LEFT),
                'requested_amount' => $amount,
                'requested_term' => $term,
                'repayment_frequency' => $plan->repayment_frequency,
                'loan_purpose' => $plan->loan_purpose,
                'purpose_description' => fake()->sentence(6),
                'application_date' => $applicationDate->format('Y-m-d'),
                'status' => LoanApplicationStatus::Approved,
                'submitted_at' => $applicationDate->format('Y-m-d H:i:s'),
                'submitted_by' => $admin->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $loan = Loan::create([
                'organization_id' => $member->organization_id,
                'branch_id' => $member->branch_id,
                'member_id' => $member->id,
                'loan_plan_id' => $plan->id,
                'loan_application_id' => $application->id,
                'disbursed_by' => $admin->id,
                'loan_number' => 'LN-'.str_pad(++$this->loanCounter, 6, '0', STR_PAD_LEFT),
                'principal_amount' => $amount,
                'disbursed_amount' => $amount,
                'interest_rate' => $interestRate,
                'interest_method' => $plan->interest_method,
                'term_months' => $term,
                'repayment_frequency' => $plan->repayment_frequency,
                'total_interest' => $totalInterest,
                'total_amount' => $totalAmount,
                'processing_fee' => $amount * ((float) $plan->processing_fee / 100),
                'insurance_fee' => $amount * ((float) $plan->insurance_fee / 100),
                'amount_paid' => 0,
                'outstanding_balance' => $totalAmount,
                'grace_period' => $plan->grace_period,
                'status' => LoanStatus::Active,
                'disbursement_date' => $disbursementDate->format('Y-m-d'),
                'maturity_date' => $maturityDate->format('Y-m-d'),
                'next_payment_date' => (clone $disbursementDate)->modify('+1 month')->format('Y-m-d'),
                'installments_paid' => 0,
                'total_installments' => $term,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $loanCount++;
            $pm = $paymentMethods->where('organization_id', $member->organization_id)->random();
            $balance = $totalAmount;

            for ($i = 1; $i <= $term; $i++) {
                $dueDate = (clone $disbursementDate)->modify("+{$i} months");
                $principalPortion = $amount / $term;
                $interestPortion = $totalInterest / $term;
                $totalDue = $principalPortion + $interestPortion;

                LoanRepaymentSchedule::create([
                    'loan_id' => $loan->id,
                    'organization_id' => $member->organization_id,
                    'installment_number' => $i,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'principal_amount' => $principalPortion,
                    'interest_amount' => $interestPortion,
                    'total_amount' => $totalDue,
                    'amount_paid' => 0,
                    'outstanding_amount' => $totalDue,
                    'running_balance' => $balance,
                    'status' => 'pending',
                ]);

                $this->scheduleCounter++;
                $balance -= $totalDue;
            }

            $paidInstallments = fake()->numberBetween(1, max(1, $term - 1));
            $totalPaid = 0;

            for ($i = 1; $i <= $paidInstallments; $i++) {
                $paymentAmount = $monthlyPayment;
                $principalPortion = $amount / $term;
                $interestPortion = $totalInterest / $term;

                $repayment = LoanRepayment::create([
                    'loan_id' => $loan->id,
                    'organization_id' => $member->organization_id,
                    'branch_id' => $member->branch_id,
                    'member_id' => $member->id,
                    'payment_method_id' => $pm->id,
                    'received_by' => $admin->id,
                    'repayment_number' => 'RPT-'.str_pad(++$this->repaymentCounter, 6, '0', STR_PAD_LEFT),
                    'amount' => $paymentAmount,
                    'principal_portion' => $principalPortion,
                    'interest_portion' => $interestPortion,
                    'fee_portion' => 0,
                    'overpayment_amount' => 0,
                    'payment_date' => (clone $disbursementDate)->modify("+{$i} months")->format('Y-m-d'),
                    'reference_number' => fake()->optional(0.5)->bothify('PAY-####'),
                    'status' => LoanRepaymentStatus::Posted,
                ]);

                $repaymentCount++;
                $totalPaid += $paymentAmount;

                $schedule = LoanRepaymentSchedule::where('loan_id', $loan->id)
                    ->where('installment_number', $i)
                    ->first();

                if ($schedule) {
                    $schedule->update([
                        'amount_paid' => $paymentAmount,
                        'outstanding_amount' => 0,
                        'status' => 'paid',
                        'paid_date' => $repayment->payment_date,
                    ]);
                }
            }

            $loan->update([
                'amount_paid' => $totalPaid,
                'outstanding_balance' => $totalAmount - $totalPaid,
                'installments_paid' => $paidInstallments,
                'next_payment_date' => (clone $disbursementDate)->modify('+'.($paidInstallments + 1).' months')->format('Y-m-d'),
            ]);
        }

        $this->command->info("  Created {$loanCount} loans with {$repaymentCount} repayments.");
    }
}
