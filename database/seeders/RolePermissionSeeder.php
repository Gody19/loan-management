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

        // AI business-data tool permissions (Phase 11.3). These gate the
        // read-only business capabilities registered in the AiToolRegistry.
        // They deliberately mirror the existing module stems so capability and
        // screen access stay aligned. No new or broadened permissions are
        // introduced for these surfaces; deliberate grants are applied below.
        foreach ([
            'ai.member.view',
            'ai.loan.view',
            'ai.loan-repayments.view',
            'ai.loan-application.view',
            'ai.loan-eligibility.view',
        ] as $aiBusinessPermission) {
            Permission::firstOrCreate([
                'name' => $aiBusinessPermission,
                'guard_name' => 'web',
            ]);
        }

        // AI knowledge-base permissions (Phase 11.5). ai.knowledge.search gates
        // approved-knowledge retrieval for anyone holding the chat capability;
        // ai.knowledge.manage gates the creation/archival of knowledge
        // documents and is deliberately withheld from VICOBA Members.
        foreach (['ai.knowledge.search', 'ai.knowledge.manage'] as $aiKnowledgePermission) {
            Permission::firstOrCreate([
                'name' => $aiKnowledgePermission,
                'guard_name' => 'web',
            ]);
        }

        // AI feedback, evaluation and learning-dataset permissions (Phase 11.6).
        // Four deliberately separate capabilities, so that receiving feedback,
        // reviewing it, approving it into the dataset, and exporting the
        // dataset are four independent grants:
        //   ai.feedback.submit   react to an AI response
        //   ai.feedback.review   read the tenant-scoped review queue
        //   ai.feedback.approve  approve/reject and admit to the dataset
        //   ai.feedback.export   download the approved dataset
        foreach ([
            'ai.feedback.submit',
            'ai.feedback.review',
            'ai.feedback.approve',
            'ai.feedback.export',
        ] as $aiFeedbackPermission) {
            Permission::firstOrCreate([
                'name' => $aiFeedbackPermission,
                'guard_name' => 'web',
            ]);
        }

        // AI Financial-Intelligence permissions (Phase 11.7). Six deliberate,
        // read-only capabilities that mirror the intelligence domains, so a
        // role can be given exactly the summaries it may see:
        //   ai.portfolio.view    portfolio composition & maturities
        //   ai.delinquency.view  PAR, aging buckets and delinquent loans
        //   ai.collection.view   collection rate, due vs collected, reversals
        //   ai.trend.view        monthly disbursement/collection series
        //   ai.accounting.view   income statement, trial balance, liquidity
        //   ai.anomaly.view      rule-based anomaly findings and their review
        foreach ([
            'ai.portfolio.view',
            'ai.delinquency.view',
            'ai.collection.view',
            'ai.trend.view',
            'ai.accounting.view',
            'ai.anomaly.view',
        ] as $aiFinancialIntelligencePermission) {
            Permission::firstOrCreate([
                'name' => $aiFinancialIntelligencePermission,
                'guard_name' => 'web',
            ]);
        }

        // AI Predictive Intelligence permission (Phase 11.8). A single,
        // read-only advisory capability covering all four statistical domains
        // (portfolio / delinquency-risk / cash-flow / collection outlooks).
        // Predictions never feed business rules and never expose member-level
        // detail. Disabled roles (Secretary, VICOBA Member) are granted nothing.
        Permission::firstOrCreate([
            'name' => 'ai.predictive.view',
            'guard_name' => 'web',
        ]);

        // AI Proactive Intelligence permission (Phase 11.9). A single,
        // read-only advisory capability covering the deterministic insight
        // and alert surface (overdue exposure, maturity pressure, portfolio
        // risk and concentration, cash-flow, collections, savings, accounting,
        // operational gaps and predictive outlooks). Insights are generated
        // by rules over authoritative records and resolved/dismissed by humans;
        // the AI assistant only reads them.
        Permission::firstOrCreate([
            'name' => 'ai.insights.view',
            'guard_name' => 'web',
        ]);

        // AI Intelligence Reporting permission (Phase 12.0). A single,
        // read-only management-reporting capability covering the six
        // deterministic report types (executive portfolio, loan performance,
        // collections, cash-flow intelligence, accounting intelligence and
        // operational intelligence) for the reader's own organizations.
        // Reports are generated by the FinancePro reporting service; the AI
        // only explains them. Granting this capability never grants the
        // underlying accounting capability — the reporting service additionally
        // requires ai.accounting.view before an accounting report or a
        // cash-flow report may be produced.
        Permission::firstOrCreate([
            'name' => 'ai.reports.view',
            'guard_name' => 'web',
        ]);

        // Scheduled management reporting (Phase 12.1): configuring the
        // automatic generation and distribution of a recurring report. This is a
        // management configuration act and is deliberately separate from the
        // read-only ai.reports.view, so a reader is not automatically able to
        // subscribe an organization to recurring delivery. It grants no financial
        // capability of its own: the scheduled run still executes under the
        // schedule owner's own context and still requires ai.reports.view (and
        // ai.accounting.view for an accounting report).
        Permission::firstOrCreate([
            'name' => 'ai.reports.schedule',
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
        | AI Chat Permissions
        |--------------------------------------------------------------------------
        |
        | Every role may use the conversation/chat capabilities (ai.view,
        | ai.use). Access is still capability-gated in Laravel via the
        | AiToolPolicy / AiGuardrailService and scoped to each user's trusted
        | context (own conversations & own organizations; VICOBA Members are
        | owner-only).
        |--------------------------------------------------------------------------
        */
        $aiChatRoles = [
            'Organization Administrator',
            'Branch Manager',
            'Loan Officer',
            'Credit Officer',
            'Treasurer',
            'Accountant',
            'Secretary',
            'Collection Officer',
            'Auditor',
            'VICOBA Member',
        ];

        foreach ($aiChatRoles as $aiRoleName) {
            Role::where('name', $aiRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo(['ai.view', 'ai.use']);
        }

        /*
        |--------------------------------------------------------------------------
        | AI Business-Data Tool Permissions
        |--------------------------------------------------------------------------
        |
        | Read-only AI business tools are gated by five capabilities that map
        | to module stems: ai.member.view, ai.loan.view,
        | ai.loan-repayments.view, ai.loan-application.view,
        | ai.loan-eligibility.view. Grants are deliberate and documented:
        |
        |   Organization Administrator  all five
        |   Branch Manager              all five
        |   Loan Officer                all five
        |   Credit Officer              all five
        |   Collection Officer          member + loan + loan-repayments
        |   Secretary                   member only
        |   VICOBA Member               all five (owner-only scope in tools)
        |   Treasurer / Accountant / Auditor   none
        |
        | Super Administrator receives every permission (including these) via
        | the syncPermissions(Permission::all()) above. Scope is always
        | enforced by AiGuardrailService / AiToolAccessService on top of the
        | existing FinancePro policies.
        |--------------------------------------------------------------------------
        */
        $aiAllFive = [
            'ai.member.view',
            'ai.loan.view',
            'ai.loan-repayments.view',
            'ai.loan-application.view',
            'ai.loan-eligibility.view',
        ];

        $aiBusinessGrants = [
            'Organization Administrator' => $aiAllFive,
            'Branch Manager' => $aiAllFive,
            'Loan Officer' => $aiAllFive,
            'Credit Officer' => $aiAllFive,
            'Collection Officer' => ['ai.member.view', 'ai.loan.view', 'ai.loan-repayments.view'],
            'Secretary' => ['ai.member.view'],
            'VICOBA Member' => $aiAllFive,
        ];

        foreach ($aiBusinessGrants as $aiRoleName => $aiPermissions) {
            Role::where('name', $aiRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo($aiPermissions);
        }

        /*
        |--------------------------------------------------------------------------
        | AI Knowledge-Base Permission Grants
        |--------------------------------------------------------------------------
        |
        | Retrieval (ai.knowledge.search) is a read-only surface served to the
        | same staff/member roles that already hold read business tools.
        | Management (ai.knowledge.manage) is limited to Organization
        | Administrators and Branch Managers; VICOBA Members can never
        | administer the knowledge base. Super Administrator receives both via
        | syncPermissions(Permission::all()) above.
        |--------------------------------------------------------------------------
        */
        $aiKnowledgeRoles = [
            'Organization Administrator',
            'Branch Manager',
            'Loan Officer',
            'Credit Officer',
            'Collection Officer',
            'Secretary',
            'VICOBA Member',
        ];

        foreach ($aiKnowledgeRoles as $aiRoleName) {
            Role::where('name', $aiRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo('ai.knowledge.search');
        }

        foreach (['Organization Administrator', 'Branch Manager'] as $aiManageRoleName) {
            Role::where('name', $aiManageRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo('ai.knowledge.manage');
        }

        /*
        |--------------------------------------------------------------------------
        | AI Feedback / Evaluation / Learning-Dataset Grants (Phase 11.6)
        |--------------------------------------------------------------------------
        |
        | ai.feedback.submit    Every role that can chat may react to a response
        |                       in their own conversation, so this mirrors the
        |                       aiChatRoles list exactly.
        |
        | ai.feedback.review    Organization Administrator, Branch Manager,
        |                       Credit Officer and Auditor. Review is an
        |                       oversight activity scoped to the reviewer's own
        |                       organization (and branch, when branch scoped).
        |                       Deliberately withheld from VICOBA Member,
        |                       Treasurer, Accountant, Secretary and
        |                       Collection Officer.
        |
        | ai.feedback.approve   Organization Administrator and Branch Manager
        |                       only. Approval is the single gate that admits an
        |                       example into the learning dataset, so it is kept
        |                       to the two most trusted operational roles.
        |
        | ai.feedback.export    Organization Administrator and Branch Manager
        |                       only, matching approval — a reviewer who cannot
        |                       approve an example cannot export the dataset
        |                       either.
        |
        | No VICOBA Member receives review, approve, or export. VICOBA Members
        | can never review another member's feedback, approve a training
        | example, or download the dataset. Super Administrator receives all
        | four via syncPermissions(Permission::all()) above.
        |--------------------------------------------------------------------------
        */
        foreach ($aiChatRoles as $aiFeedbackSubmitRoleName) {
            Role::where('name', $aiFeedbackSubmitRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo('ai.feedback.submit');
        }

        foreach ([
            'Organization Administrator',
            'Branch Manager',
            'Credit Officer',
            'Auditor',
        ] as $aiFeedbackReviewRoleName) {
            Role::where('name', $aiFeedbackReviewRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo('ai.feedback.review');
        }

        foreach (['Organization Administrator', 'Branch Manager'] as $aiFeedbackApproveRoleName) {
            Role::where('name', $aiFeedbackApproveRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo(['ai.feedback.approve', 'ai.feedback.export']);
        }

        /*
        |--------------------------------------------------------------------------
        | AI Financial-Intelligence Permission Grants (Phase 11.7)
        |--------------------------------------------------------------------------
        |
        | Read-only intelligence summaries are granted per the access matrix
        | from the Phase 11.7 specification. VICOBA Members never receive any
        | of them — their AI scope stays owner-only. Super Administrator
        | receives all six via syncPermissions(Permission::all()) above.
        |
        |   Organization Administrator  portfolio, delinquency, collection, trend, accounting, anomaly
        |   Branch Manager              portfolio, delinquency, collection, trend, accounting, anomaly
        |   Loan Officer                portfolio, delinquency, collection, trend
        |   Credit Officer              portfolio, delinquency, collection, trend
        |   Collection Officer          portfolio, delinquency, collection
        |   Treasurer                   collection, trend, accounting
        |   Accountant                  trend, accounting
        |   Auditor                     portfolio, delinquency, collection, trend, accounting, anomaly
        |   Secretary                   none
        |   VICOBA Member               none
        |
        | Scope is always enforced server-side from the trusted AI context
        | (organization / branch memberships); the browser never supplies a
        | tenant. All six capabilities are read-only and re-gated by the
        | AiToolPolicy before any tool runs.
        |--------------------------------------------------------------------------
        */
        $aiIntelligenceAll = [
            'ai.portfolio.view',
            'ai.delinquency.view',
            'ai.collection.view',
            'ai.trend.view',
            'ai.accounting.view',
            'ai.anomaly.view',
        ];

        $aiIntelligenceGrants = [
            'Organization Administrator' => $aiIntelligenceAll,
            'Branch Manager' => $aiIntelligenceAll,
            'Loan Officer' => ['ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view', 'ai.trend.view'],
            'Credit Officer' => ['ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view', 'ai.trend.view'],
            'Collection Officer' => ['ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view'],
            'Treasurer' => ['ai.collection.view', 'ai.trend.view', 'ai.accounting.view'],
            'Accountant' => ['ai.trend.view', 'ai.accounting.view'],
            'Auditor' => $aiIntelligenceAll,
        ];

        foreach ($aiIntelligenceGrants as $aiIntelligenceRoleName => $aiIntelligencePermissions) {
            Role::where('name', $aiIntelligenceRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo($aiIntelligencePermissions);
        }

        /*
        | Predictive Intelligence grants (Phase 11.8): the staff roles that can
        | already read their organizations' financial intelligence may also
        | read the advisory predictive outlooks. Secretary and VICOBA Member
        | hold none — predictions are staff-facing aggregate signal only.
        |--------------------------------------------------------------------------
        */
        $aiPredictiveGrants = [
            'Organization Administrator' => ['ai.predictive.view'],
            'Branch Manager' => ['ai.predictive.view'],
            'Loan Officer' => ['ai.predictive.view'],
            'Credit Officer' => ['ai.predictive.view'],
            'Collection Officer' => ['ai.predictive.view'],
            'Treasurer' => ['ai.predictive.view'],
            'Accountant' => ['ai.predictive.view'],
            'Auditor' => ['ai.predictive.view'],
        ];

        foreach ($aiPredictiveGrants as $aiPredictiveRoleName => $aiPredictivePermissions) {
            Role::where('name', $aiPredictiveRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo($aiPredictivePermissions);
        }

        /*
        | Proactive Intelligence grants (Phase 11.9): the same staff roles that
        | can read their organizations' predictive outlooks may also read and
        | act on proactive insights/alerts. Secretary and VICOBA Member hold
        | none — the alert surface is staff-facing advisory signal only.
        |--------------------------------------------------------------------------
        */
        $aiInsightsGrants = [
            'Organization Administrator' => ['ai.insights.view'],
            'Branch Manager' => ['ai.insights.view'],
            'Loan Officer' => ['ai.insights.view'],
            'Credit Officer' => ['ai.insights.view'],
            'Collection Officer' => ['ai.insights.view'],
            'Treasurer' => ['ai.insights.view'],
            'Accountant' => ['ai.insights.view'],
            'Auditor' => ['ai.insights.view'],
        ];

        foreach ($aiInsightsGrants as $aiInsightsRoleName => $aiInsightsPermissions) {
            Role::where('name', $aiInsightsRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo($aiInsightsPermissions);
        }

        /*
        | Management reporting grants (Phase 12.0): the same staff roles that
        | can read their organizations' insights may also generate and read the
        | structured management intelligence reports. Secretary and VICOBA Member
        | hold none — management reporting is a staff-facing surface, and the
        | accounting report additionally re-checks ai.accounting.view inside the
        | reporting service rather than being granted by this capability.
        |--------------------------------------------------------------------------
        */
        $aiReportsGrants = [
            'Organization Administrator' => ['ai.reports.view'],
            'Branch Manager' => ['ai.reports.view'],
            'Loan Officer' => ['ai.reports.view'],
            'Credit Officer' => ['ai.reports.view'],
            'Collection Officer' => ['ai.reports.view'],
            'Treasurer' => ['ai.reports.view'],
            'Accountant' => ['ai.reports.view'],
            'Auditor' => ['ai.reports.view'],
        ];

        foreach ($aiReportsGrants as $aiReportsRoleName => $aiReportsPermissions) {
            Role::where('name', $aiReportsRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo($aiReportsPermissions);
        }

        /*
        |--------------------------------------------------------------------------
        | Scheduled reporting grants (Phase 12.1)
        |--------------------------------------------------------------------------
        */
        // Recurring delivery is a management configuration act, so it is granted
        // more narrowly than reading a report. Only the organization
        // administrator, the branch manager and the auditor may configure it:
        // the administrator and branch manager own their organization's
        // operations, and the auditor schedules recurring oversight. Officers,
        // the treasurer, the accountant, the Secretary and VICOBA Members may
        // read reports (where granted above) but may not subscribe an
        // organization to automatic distribution.
        $aiReportScheduleGrants = [
            'Organization Administrator' => ['ai.reports.schedule'],
            'Branch Manager' => ['ai.reports.schedule'],
            'Auditor' => ['ai.reports.schedule'],
        ];

        foreach ($aiReportScheduleGrants as $aiScheduleRoleName => $aiSchedulePermissions) {
            Role::where('name', $aiScheduleRoleName)->where('guard_name', 'web')
                ->firstOrFail()
                ->givePermissionTo($aiSchedulePermissions);
        }

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
