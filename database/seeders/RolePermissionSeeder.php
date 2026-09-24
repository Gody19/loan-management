<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Create Permissions
        |--------------------------------------------------------------------------
        */
        $modules = [
            'user',
            'role',
            'permission',
            'organization',
            'dashboard',
            'member',
            'group',
            'branch',
            'savings',
            'savings_product',
            'savings_account',
            'savings_transaction',
            'shares',
            'share_product',
            'share_account',
            'share_transaction',
            'welfare_fund',
            'welfare_account',
            'welfare_transaction',
            'payment_method',
            'loan_plan',
            'loan_eligibility',
            'loan_application',
            'loan_approval_level',
            'loans',
            'loan-repayments',
            'accounting',
            'meetings',
            'reports',
            'settings',
            'audit',
            'contact_message',
            'ai',
        ];

        $actions = [
            'view',
            'create',
            'update',
            'delete',
            'assign',
            'approve',
            'export',
            'import',
            'post',
            'reverse',
            'manage',
        ];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // The 'ai' module adds a 'use' action not in the shared action list. It
        // is created here explicitly so that only the AI capability is affected;
        // other modules keep their existing action matrix.
        Permission::firstOrCreate([
            'name' => 'ai.use',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create Roles
        |--------------------------------------------------------------------------
        */
        $allPermissions = Permission::all();

        // Super Administrator - has all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($allPermissions);

        // Organization Administrator
        $orgAdmin = Role::firstOrCreate(['name' => 'Organization Administrator', 'guard_name' => 'web']);
        $orgAdmin->syncPermissions($allPermissions->filter(fn ($p) => ! str_contains($p->name, 'audit.') &&
            in_array(explode('.', $p->name)[0], [
                'dashboard', 'organization', 'branch', 'group',
                'member', 'savings', 'savings_product', 'savings_account', 'savings_transaction',
                'shares', 'share_product', 'share_account', 'share_transaction',
                'welfare_fund', 'welfare_account', 'welfare_transaction', 'payment_method',
                'loan_plan', 'loan_eligibility', 'loan_application', 'loan_approval_level',
                'loans', 'loan-repayments', 'accounting', 'meetings', 'reports', 'settings',
                'user', 'role',
            ])
        ));

        // Branch Manager
        $branchManager = Role::firstOrCreate(['name' => 'Branch Manager', 'guard_name' => 'web']);
        $branchManager->syncPermissions($allPermissions->filter(fn ($p) => in_array(explode('.', $p->name)[0], [
            'dashboard', 'branch', 'group',
            'member', 'savings', 'savings_account', 'savings_transaction',
            'shares', 'share_account', 'share_transaction',
            'welfare_account', 'welfare_transaction',
            'loan_plan', 'loan_eligibility', 'loan_application',
            'loans', 'loan-repayments', 'meetings', 'reports',
        ])
        ));

        // Loan Officer
        $loanOfficer = Role::firstOrCreate(['name' => 'Loan Officer', 'guard_name' => 'web']);
        $loanOfficer->syncPermissions($allPermissions->filter(fn ($p) => in_array($p->name, ['dashboard.view', 'member.view', 'savings.view', 'savings_account.view', 'shares.view', 'share_account.view', 'loan_plan.view', 'loan_eligibility.view', 'loan_application.view', 'loan_application.create', 'loan_application.update', 'loans.view', 'loans.create', 'loans.update', 'loan-repayments.view', 'loan-repayments.create', 'reports.view'])
        ));

        // Credit Officer
        $creditOfficer = Role::firstOrCreate(['name' => 'Credit Officer', 'guard_name' => 'web']);
        $creditOfficer->syncPermissions($allPermissions->filter(fn ($p) => in_array($p->name, ['dashboard.view', 'member.view', 'savings.view', 'savings_account.view', 'shares.view', 'share_account.view', 'loan_plan.view', 'loan_eligibility.view', 'loan_application.view', 'loan_application.create', 'loan_application.update', 'loans.view', 'loans.create', 'loans.update', 'loans.approve', 'reports.view'])
        ));

        // Treasurer
        $treasurer = Role::firstOrCreate(['name' => 'Treasurer', 'guard_name' => 'web']);
        $treasurer->syncPermissions($allPermissions->filter(fn ($p) => in_array(explode('.', $p->name)[0], [
            'dashboard', 'savings', 'savings_product', 'savings_account', 'savings_transaction',
            'shares', 'share_product', 'share_account', 'share_transaction',
            'welfare_fund', 'welfare_account', 'welfare_transaction', 'payment_method',
            'accounting', 'reports',
        ])
        ));

        // Accountant
        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions($allPermissions->filter(fn ($p) => in_array(explode('.', $p->name)[0], ['dashboard', 'accounting', 'reports'])
        ));

        // Secretary
        $secretary = Role::firstOrCreate(['name' => 'Secretary', 'guard_name' => 'web']);
        $secretary->syncPermissions($allPermissions->filter(fn ($p) => in_array(explode('.', $p->name)[0], ['dashboard', 'member', 'group', 'meetings', 'reports'])
        ));

        // Collection Officer
        $collectionOfficer = Role::firstOrCreate(['name' => 'Collection Officer', 'guard_name' => 'web']);
        $collectionOfficer->syncPermissions($allPermissions->filter(fn ($p) => in_array($p->name, ['dashboard.view', 'member.view', 'savings.view', 'savings.create', 'loans.view', 'loan-repayments.view', 'loan-repayments.create', 'reports.view'])
        ));

        // Auditor
        $auditor = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        $auditor->syncPermissions($allPermissions->filter(fn ($p) => in_array(explode('.', $p->name)[0], ['dashboard', 'reports', 'audit'])
        ));

        // VICOBA Member
        $member = Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);
        $member->syncPermissions($allPermissions->filter(fn ($p) => in_array($p->name, ['dashboard.view', 'savings.view', 'shares.view', 'loan_application.view', 'loans.view', 'loan-repayments.view'])
        ));

        /*
        |--------------------------------------------------------------------------
        | Create Default Super Administrator
        |--------------------------------------------------------------------------
        */
        $admin = User::firstOrCreate(
            ['email' => 'admin@financepro.co.tz'],
            [
                'fullname' => 'System Administrator',
                'username' => 'admin',
                'phone' => '+255700000000',
                'nida_number' => '00000000000000000000',
                'status' => 'active',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('Super Administrator');

        /*
        |--------------------------------------------------------------------------
        | Create Test User
        |--------------------------------------------------------------------------
        */
        $testUser = User::firstOrCreate(
            ['email' => 'test@financepro.co.tz'],
            [
                'fullname' => 'Test User',
                'username' => 'testuser',
                'phone' => '+255711111111',
                'nida_number' => '11111111111111111111',
                'status' => 'active',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $testUser->assignRole('VICOBA Member');
    }
}
