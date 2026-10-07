<?php

namespace Database\Seeders;

use App\Enums\AccountingPeriodStatus;
use App\Enums\BranchStatus;
use App\Enums\GroupStatus;
use App\Enums\InterestMethod;
use App\Enums\LoanPlanStatus;
use App\Enums\LoanPurpose;
use App\Enums\PaymentMethodType;
use App\Enums\RepaymentFrequency;
use App\Enums\SavingsAccountStatus;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\LoanApprovalLevel;
use App\Models\LoanPlan;
use App\Models\LoanPlanCollateralRule;
use App\Models\PaymentMethod;
use App\Models\SavingsProduct;
use App\Models\ShareProduct;
use App\Models\VicobaGroup;
use App\Models\WelfareFund;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $this->branches();
        $this->groups();
        $this->paymentMethods();
        $this->savingsProducts();
        $this->shareProducts();
        $this->welfareFunds();
        $this->loanPlans();
        $this->approvalLevels();
        $this->collateralRules();
        $this->accountingPeriods();

        $this->report('Reference data ready.');
    }

    private function branches(): void
    {
        $names = ['Kinondoni', 'Temeke', 'Ilala', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya', 'Tanga', 'Morogoro', 'Zanzibar'];
        $regions = ['Dar es Salaam', 'Dar es Salaam', 'Dar es Salaam', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya', 'Tanga', 'Morogoro', 'Zanzibar'];

        foreach ($names as $index => $name) {
            if ($this->gap('branches', 10) <= 0) {
                break;
            }

            $code = sprintf('BR-FP-%03d', $index + 2);

            if (Branch::where('organization_id', self::ORG_ID)->where('code', $code)->exists()) {
                continue;
            }

            Branch::create([
                'organization_id' => self::ORG_ID,
                'code' => $code,
                'name' => $name.' Branch',
                'phone' => '+2557'.fake()->unique()->numerify('########'),
                'address' => fake()->streetAddress().', '.$regions[$index],
                'manager' => fake()->name(),
                'status' => BranchStatus::Active,
            ]);

            $this->bump('branches');
        }
    }

    private function groups(): void
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $times = ['15:00:00', '16:30:00', '17:00:00', '18:00:00'];
        $locations = ['Kijitonyama Hall', 'Mwakalilo Hall', 'Mnzani Hall', 'Kimwanyi Hall', 'Chamazi Hall'];
        $branches = Branch::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $sequence = 0;

        foreach ($branches as $branchId) {
            while ($this->gap('vicoba_groups', 20) > 0) {
                $sequence++;
                $code = sprintf('GRP-FP-%02d-%02d', $branchId, $sequence);

                if (VicobaGroup::where('branch_id', $branchId)->where('code', $code)->exists()) {
                    continue;
                }

                VicobaGroup::create([
                    'branch_id' => $branchId,
                    'code' => $code,
                    'name' => 'Umoja '.fake()->unique()->numerify('##').' Savings Group',
                    'meeting_day' => $this->pick($days),
                    'meeting_time' => $this->pick($times),
                    'meeting_location' => $this->pick($locations),
                    'description' => 'Weekly member savings and lending group.',
                    'status' => GroupStatus::Active,
                ]);

                $this->bump('vicoba_groups');
            }
        }
    }

    private function paymentMethods(): void
    {
        $methods = [
            ['Cash', 'CASH', PaymentMethodType::Cash],
            ['Bank Transfer', 'BANK', PaymentMethodType::Bank],
            ['Mobile Money', 'MOMO', PaymentMethodType::MobileMoney],
            ['Card Payment', 'CARD', PaymentMethodType::Card],
            ['Airtel Money', 'AIRTEL', PaymentMethodType::MobileMoney],
            ['Mixed Payment', 'MIXED', PaymentMethodType::Other],
        ];

        foreach ($methods as [$name, $code, $type]) {
            if ($this->gap('payment_methods', 6) <= 0) {
                break;
            }

            if (PaymentMethod::where('organization_id', self::ORG_ID)->where('code', $code)->exists()) {
                continue;
            }

            PaymentMethod::create([
                'organization_id' => self::ORG_ID,
                'name' => $name,
                'code' => $code,
                'type' => $type,
                'status' => 'active',
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('payment_methods');
        }
    }

    private function savingsProducts(): void
    {
        $products = [
            ['Standard Savings', 'SP-FP-001', 10000, 5000000, 10000, 500000, 'Everyday savings with monthly interest crediting.'],
            ['Flexible Savings', 'SP-FP-002', 5000, 2000000, 5000, 300000, 'Deposits allowed at any time with flexible withdrawals.'],
            ['Fixed Deposit', 'SP-FP-003', 50000, 10000000, 0, 0, 'Locked deposit with a premium interest rate.'],
            ['Junior Savings', 'SP-FP-004', 2000, 500000, 2000, 50000, 'Low balance account for members under 25 years.'],
            ['Emergency Fund', 'SP-FP-005', 10000, 1000000, 10000, 200000, 'Restricted emergency reserve account.'],
        ];

        foreach ($products as [$name, $code, $min, $max, $minBalance, $limit, $description]) {
            if ($this->gap('savings_products', 5) <= 0) {
                break;
            }

            if (SavingsProduct::where('organization_id', self::ORG_ID)->where('code', $code)->exists()) {
                continue;
            }

            SavingsProduct::create([
                'organization_id' => self::ORG_ID,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'minimum_amount' => $min,
                'maximum_amount' => $max,
                'minimum_balance' => $minBalance,
                'allow_withdrawal' => $limit > 0 ? 1 : 0,
                'withdrawal_limit' => $limit > 0 ? $limit : null,
                'status' => SavingsAccountStatus::Active,
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('savings_products');
        }
    }

    private function shareProducts(): void
    {
        $products = [
            ['Ordinary Shares', 'SH-FP-001', 1000, 10, 5000, 'Standard member shares with an annual dividend.'],
            ['Preferred Shares', 'SH-FP-002', 2500, 5, 2000, 'Preferred shares with an enhanced dividend rate.'],
            ['Premium Shares', 'SH-FP-003', 5000, 2, 500, 'Premium entry tier for institutional members.'],
        ];

        foreach ($products as [$name, $code, $price, $minShares, $maxShares, $description]) {
            if ($this->gap('share_products', 3) <= 0) {
                break;
            }

            if (ShareProduct::where('organization_id', self::ORG_ID)->where('code', $code)->exists()) {
                continue;
            }

            ShareProduct::create([
                'organization_id' => self::ORG_ID,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'share_price' => $price,
                'minimum_shares' => $minShares,
                'maximum_shares' => $maxShares,
                'status' => SavingsAccountStatus::Active,
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('share_products');
        }
    }

    private function welfareFunds(): void
    {
        $funds = [
            ['Group Savings Fund', 'WF-FP-001', 50000, 'Monthly group savings contributions for all members.'],
            ['Education Fund', 'WF-FP-002', 25000, 'Dedicated fund supporting members school fees.'],
            ['Emergency Fund', 'WF-FP-003', 10000, 'Rapid support fund for member emergencies.'],
        ];

        foreach ($funds as [$name, $code, $amount, $description]) {
            if ($this->gap('welfare_funds', 3) <= 0) {
                break;
            }

            if (WelfareFund::where('organization_id', self::ORG_ID)->where('code', $code)->exists()) {
                continue;
            }

            WelfareFund::create([
                'organization_id' => self::ORG_ID,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'contribution_type' => 'fixed',
                'default_amount' => $amount,
                'status' => 'active',
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('welfare_funds');
        }
    }

    private function loanPlans(): void
    {
        $plans = [
            ['Micro Enterprise Loan', 'LP-FP-001', LoanPurpose::Business, 50000, 2000000, 14.0000, InterestMethod::ReducingBalance, 6, 24, RepaymentFrequency::Monthly, 1, 0, 0, 50000, 3.00, 2.00, 0, 2500, 1000, 'Working capital for small scale trading businesses.'],
            ['Group Development Loan', 'LP-FP-002', LoanPurpose::Development, 200000, 5000000, 15.0000, InterestMethod::ReducingBalance, 12, 36, RepaymentFrequency::Monthly, 1, 2, 0, 100000, 3.00, 2.00, 0, 5000, 2000, 'Development loan secured by a group guarantee.'],
            ['Education Loan', 'LP-FP-003', LoanPurpose::Education, 100000, 3000000, 10.0000, InterestMethod::Flat, 6, 24, RepaymentFrequency::Monthly, 2, 0, 0, 0, 0, 0, 0, 3000, 0, 'Loan covering school fees and learning materials.'],
            ['Agriculture Loan', 'LP-FP-004', LoanPurpose::Agriculture, 150000, 4000000, 13.0000, InterestMethod::ReducingBalance, 12, 36, RepaymentFrequency::Monthly, 1, 0, 1, 50000, 2.00, 1.50, 0, 4000, 1500, 'Seasonal agriculture loan with a collateral requirement.'],
            ['Emergency Loan', 'LP-FP-005', LoanPurpose::Emergency, 20000, 500000, 18.0000, InterestMethod::Flat, 3, 12, RepaymentFrequency::Monthly, 2, 0, 0, 0, 0, 0, 1, 1000, 0, 'Short term emergency facility with a one month grace period.'],
        ];

        foreach ($plans as [$name, $code, $purpose, $min, $max, $rate, $method, $minTerm, $maxTerm, $frequency, $maxActive, $guarantors, $collateral, $minSavings, $savingsMultiplier, $shareMultiplier, $grace, $processingFee, $insuranceFee, $description]) {
            if ($this->gap('loan_plans', 5) <= 0) {
                break;
            }

            if (LoanPlan::where('organization_id', self::ORG_ID)->where('code', $code)->exists()) {
                continue;
            }

            LoanPlan::create([
                'organization_id' => self::ORG_ID,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'loan_purpose' => $purpose,
                'minimum_amount' => $min,
                'maximum_amount' => $max,
                'interest_rate' => $rate,
                'interest_method' => $method,
                'minimum_term' => $minTerm,
                'maximum_term' => $maxTerm,
                'repayment_frequency' => $frequency,
                'maximum_active_loans' => $maxActive,
                'requires_guarantor' => $guarantors > 0 ? 1 : 0,
                'minimum_guarantors' => $guarantors,
                'requires_collateral' => $collateral,
                'minimum_savings_balance' => $minSavings,
                'savings_multiplier' => $savingsMultiplier,
                'share_multiplier' => $shareMultiplier,
                'maximum_loan_to_savings_ratio' => 0,
                'grace_period' => $grace,
                'processing_fee' => $processingFee,
                'insurance_fee' => $insuranceFee,
                'late_payment_allowed' => 1,
                'status' => LoanPlanStatus::Active,
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('loan_plans');
        }
    }

    private function approvalLevels(): void
    {
        $levels = [
            ['Committee Level One', 0, 500000, 1, 'loan_application.approve'],
            ['Committee Level Two', 500000, 2000000, 2, 'loans.approve'],
            ['Credit Committee', 2000000, 5000000, 3, 'loan_eligibility.approve'],
            ['Board Approval', 5000000, 100000000, 4, 'loans.approve'],
        ];

        foreach ($levels as [$name, $min, $max, $level, $permission]) {
            if ($this->gap('loan_approval_levels', 4) <= 0) {
                break;
            }

            if (LoanApprovalLevel::where('organization_id', self::ORG_ID)->where('name', $name)->exists()) {
                continue;
            }

            LoanApprovalLevel::create([
                'organization_id' => self::ORG_ID,
                'name' => $name,
                'minimum_amount' => $min,
                'maximum_amount' => $max,
                'level' => $level,
                'required_permission' => $permission,
                'is_active' => 1,
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('loan_approval_levels');
        }
    }

    private function collateralRules(): void
    {
        $templates = [
            ['minimum_amount' => 0, 'maximum_amount' => 1500000, 'collateral_required' => false, 'coverage_percentage' => 100.00, 'minimum_collateral_value' => 0, 'minimum_assets' => 0, 'maximum_assets' => 0, 'allowed_collateral_types' => [], 'required_document_types' => [], 'description' => 'No collateral required for amounts within this tier.'],
            ['minimum_amount' => 1500000, 'maximum_amount' => 50000000, 'collateral_required' => true, 'coverage_percentage' => 150.00, 'minimum_collateral_value' => 2250000, 'minimum_assets' => 1, 'maximum_assets' => 5, 'allowed_collateral_types' => ['land', 'vehicle', 'building', 'equipment'], 'required_document_types' => ['ownership_document', 'valuation_report', 'photographs'], 'description' => 'Collateral must cover the requested amount at 150 percent.'],
        ];

        $plans = LoanPlan::where('organization_id', self::ORG_ID)->orderBy('id')->get();

        foreach ($plans as $plan) {
            foreach ($templates as $template) {
                if ($this->gap('loan_plan_collateral_rules', 10) <= 0) {
                    return;
                }

                $exists = LoanPlanCollateralRule::where('loan_plan_id', $plan->id)
                    ->where('minimum_amount', $template['minimum_amount'])
                    ->where('maximum_amount', $template['maximum_amount'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                LoanPlanCollateralRule::create([
                    'loan_plan_id' => $plan->id,
                    'minimum_amount' => $template['minimum_amount'],
                    'maximum_amount' => $template['maximum_amount'],
                    'collateral_required' => $template['collateral_required'],
                    'coverage_percentage' => $template['coverage_percentage'],
                    'minimum_collateral_value' => $template['minimum_collateral_value'],
                    'minimum_assets' => $template['minimum_assets'],
                    'maximum_assets' => $template['maximum_assets'],
                    'allowed_collateral_types' => $template['allowed_collateral_types'],
                    'required_document_types' => $template['required_document_types'],
                    'description' => $template['description'],
                    'status' => 1,
                    'created_by' => self::ACTOR_ID,
                ]);

                $this->bump('loan_plan_collateral_rules');
            }
        }
    }

    private function accountingPeriods(): void
    {
        $currentMonth = (int) now()->month;

        for ($month = 1; $month <= 12; $month++) {
            if ($this->gap('accounting_periods', 12) <= 0) {
                break;
            }

            $start = now()->parse(sprintf('2026-%02d-01', $month));
            $name = $start->format('F Y');

            if (AccountingPeriod::where('organization_id', self::ORG_ID)->where('name', $name)->exists()) {
                continue;
            }

            $closed = $month < $currentMonth;
            $end = $start->copy()->endOfMonth()->toDateString();

            AccountingPeriod::create([
                'organization_id' => self::ORG_ID,
                'name' => $name,
                'start_date' => $start->toDateString(),
                'end_date' => $end,
                'status' => $closed ? AccountingPeriodStatus::Closed : AccountingPeriodStatus::Open,
                'closed_at' => $closed ? $end.' 18:00:00' : null,
                'closed_by' => $closed ? self::ACTOR_ID : null,
            ]);

            $this->bump('accounting_periods');
        }
    }
}
