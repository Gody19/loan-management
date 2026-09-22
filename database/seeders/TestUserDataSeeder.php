<?php

namespace Database\Seeders;

use App\Enums\LoanPlanStatus;
use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TestUserDataSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $orgAdminRole = Role::firstOrCreate(['name' => 'Organization Administrator', 'guard_name' => 'web']);
        $memberRole = Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);

        // 1. Super Admin
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@financepro.co.tz'],
            [
                'fullname' => 'System Administrator',
                'username' => 'admin',
                'phone' => '+255700000000',
                'nida_number' => '00000000000000000000',
                'status' => 'active',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $adminUser->syncRoles([$superAdminRole]);

        // 2. Organization Administrator
        $org = Organization::firstOrCreate(
            ['registration_number' => 'ORG-FP-001'],
            [
                'name' => 'FinancePro VICOBA',
                'phone' => '+255712345678',
                'email' => 'info@financepro.co.tz',
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
                'status' => 'active',
            ]
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'BR-FP-001'],
            [
                'organization_id' => $org->id,
                'name' => 'Head Office',
                'status' => 'active',
            ]
        );

        $group = VicobaGroup::firstOrCreate(
            ['name' => 'FinancePro Main Group'],
            [
                'branch_id' => $branch->id,
                'code' => 'GRP-FP-001',
                'status' => 'active',
            ]
        );

        $orgAdminUser = User::firstOrCreate(
            ['email' => 'gody12919@gmail.com'],
            [
                'fullname' => 'Godius M',
                'username' => 'gody12919',
                'phone' => '+255712345679',
                'nida_number' => '201901010001',
                'status' => 'active',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $orgAdminUser->syncRoles([$orgAdminRole]);

        // 3. VICOBA Member
        $memberUser = User::firstOrCreate(
            ['email' => 'irenedeusi55@gmail.com'],
            [
                'fullname' => 'Irene Deusi',
                'username' => 'irenedeusi',
                'phone' => '+255734567890',
                'nida_number' => '201901010002',
                'status' => 'active',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $member = Member::firstOrCreate(
            ['user_id' => $memberUser->id],
            [
                'organization_id' => $org->id,
                'branch_id' => $branch->id,
                'vicoba_group_id' => $group->id,
                'member_number' => 'MBR-FP-001',
                'first_name' => 'Irene',
                'last_name' => 'Deusi',
                'phone' => '+255734567890',
                'gender' => 'female',
                'date_of_birth' => '1990-05-15',
                'membership_status' => MemberStatus::Active,
                'joining_date' => now()->subYear(),
            ]
        );
        $memberUser->syncRoles([$memberRole]);

        // 4. Loan plans for FinancePro VICOBA org
        $plans = [
            ['name' => 'Standard Loan', 'code' => 'FP-STD', 'minimum_amount' => 50000, 'maximum_amount' => 5000000, 'interest_rate' => 10, 'interest_method' => 'flat', 'minimum_term' => 3, 'maximum_term' => 24, 'repayment_frequency' => 'monthly', 'maximum_active_loans' => 1, 'requires_guarantor' => true, 'minimum_guarantors' => 1, 'grace_period' => 5, 'processing_fee' => 1, 'insurance_fee' => 0.5],
            ['name' => 'Emergency Loan', 'code' => 'FP-EMG', 'minimum_amount' => 10000, 'maximum_amount' => 500000, 'interest_rate' => 5, 'interest_method' => 'flat', 'minimum_term' => 1, 'maximum_term' => 6, 'repayment_frequency' => 'monthly', 'maximum_active_loans' => 1, 'requires_guarantor' => false, 'minimum_guarantors' => 0, 'grace_period' => 3, 'processing_fee' => 0.5, 'insurance_fee' => 0],
            ['name' => 'Business Loan', 'code' => 'FP-BIZ', 'minimum_amount' => 500000, 'maximum_amount' => 20000000, 'interest_rate' => 12, 'interest_method' => 'reducing_balance', 'minimum_term' => 6, 'maximum_term' => 36, 'repayment_frequency' => 'monthly', 'maximum_active_loans' => 2, 'requires_guarantor' => true, 'minimum_guarantors' => 2, 'grace_period' => 7, 'processing_fee' => 2, 'insurance_fee' => 1],
            ['name' => 'Education Loan', 'code' => 'FP-EDU', 'minimum_amount' => 100000, 'maximum_amount' => 5000000, 'interest_rate' => 8, 'interest_method' => 'flat', 'minimum_term' => 6, 'maximum_term' => 24, 'repayment_frequency' => 'monthly', 'maximum_active_loans' => 1, 'requires_guarantor' => true, 'minimum_guarantors' => 1, 'grace_period' => 10, 'processing_fee' => 1, 'insurance_fee' => 0.5],
            ['name' => 'Agricultural Loan', 'code' => 'FP-AGR', 'minimum_amount' => 100000, 'maximum_amount' => 10000000, 'interest_rate' => 7, 'interest_method' => 'flat', 'minimum_term' => 3, 'maximum_term' => 18, 'repayment_frequency' => 'monthly', 'maximum_active_loans' => 1, 'requires_guarantor' => true, 'minimum_guarantors' => 1, 'grace_period' => 15, 'processing_fee' => 1, 'insurance_fee' => 0.5],
            ['name' => 'Group Loan', 'code' => 'FP-GRP', 'minimum_amount' => 10000, 'maximum_amount' => 1000000, 'interest_rate' => 6, 'interest_method' => 'flat', 'minimum_term' => 3, 'maximum_term' => 12, 'repayment_frequency' => 'monthly', 'maximum_active_loans' => 1, 'requires_guarantor' => false, 'minimum_guarantors' => 0, 'grace_period' => 5, 'processing_fee' => 0, 'insurance_fee' => 0],
        ];

        foreach ($plans as $planData) {
            LoanPlan::firstOrCreate(
                ['code' => $planData['code']],
                array_merge($planData, [
                    'organization_id' => $org->id,
                    'loan_purpose' => 'personal',
                    'minimum_savings_balance' => 0,
                    'savings_multiplier' => 3,
                    'share_multiplier' => 0,
                    'maximum_loan_to_savings_ratio' => 5,
                    'requires_collateral' => false,
                    'late_payment_allowed' => true,
                    'status' => 'active',
                ])
            );
        }

        $this->command->info("Test users and FinancePro VICOBA org seeded successfully.");
        $this->command->info("  admin@financepro.co.tz / password (Super Administrator)");
        $this->command->info("  gody12919@gmail.com / password (Organization Administrator)");
        $this->command->info("  irenedeusi55@gmail.com / password (VICOBA Member)");
    }
}
