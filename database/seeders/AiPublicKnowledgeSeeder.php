<?php

namespace Database\Seeders;

use App\AI\Services\AiKnowledgeIngestionService;
use App\Enums\AiKnowledgeDocumentType;
use App\Enums\AiKnowledgeScope;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the public landing-page knowledge base (Phase 11.7.1).
 *
 * Every document is published with visibility=Public (no tenant columns), so
 * it is the ONLY knowledge the anonymous landing-page assistant may retrieve,
 * and is processed synchronously (temporary sync queue) so the documents are
 * active and embedded immediately after seeding. Content intentionally
 * describes only features that already exist in FinancePro; nothing is
 * invented. Idempotent per content: ingestion is checksum-verified.
 */
class AiPublicKnowledgeSeeder extends Seeder
{
    /**
     * @return array<int, array{title: string, type: AiKnowledgeDocumentType, content: string, description: string}>
     */
    protected function documents(): array
    {
        return [
            [
                'title' => 'What is FinancePro?',
                'type' => AiKnowledgeDocumentType::General,
                'description' => 'Short platform overview for new visitors.',
                'content' => 'FinancePro is a centralized financial management platform built for VICOBA '
                    .'groups and microfinance organizations. It helps you manage organizations, branches '
                    .'and VICOBA groups, register and track members, operate savings, shares and welfare '
                    .'accounts, run the complete loan lifecycle from plans and eligibility to applications, '
                    .'approvals, disbursement and repayment collection, monitor delinquency, and keep '
                    .'accurate accounting records with chart of accounts, journal entries, accounting '
                    .'periods and standard reports such as the general ledger, trial balance, balance '
                    .'sheet, income statement and cash ledger. Access is controlled through roles and '
                    .'permissions, and members can sign in to their own portal to view statements, '
                    .'savings, shares, welfare and loans and make selected transactions online.',
            ],
            [
                'title' => 'FinancePro features overview',
                'type' => AiKnowledgeDocumentType::General,
                'description' => 'What staff and members can do on the platform.',
                'content' => 'FinancePro is organized per organization, branch and VICOBA group. Staff and '
                    .'administrators manage user roles and permissions, organizations, branches and groups. '
                    .'The financial modules cover savings accounts with deposits and withdrawals, share '
                    .'accounts with purchases and redemptions, welfare funds with contributions and benefit '
                    .'requests, and payment methods. The loan module supports loan plans, loan eligibility '
                    .'checks, loan applications with guarantors and collateral and document uploads, '
                    .'configurable approval levels, disbursements, repayment scheduling and repayment '
                    .'collection either by the member or on the member\'s behalf, with posted and reversed '
                    .'records clearly distinguished. Staff can view portfolio, delinquency, collection '
                    .'and trend summaries plus an accounting summary through the AI financial intelligence '
                    .'dashboard. Members use a self-service portal for their own savings, shares, welfare, '
                    .'loans, repayment schedules, statements, documents and notifications. The platform '
                    .'also includes an approved knowledge base that delivers policies and FAQs, and '
                    .'members and visitors can send messages through the contact page.',
            ],
            [
                'title' => 'What is a VICOBA group?',
                'type' => AiKnowledgeDocumentType::Faq,
                'description' => 'Definition of a Village Community Bank.',
                'content' => 'VICOBA stands for Village Community Bank. A VICOBA group is a community-based '
                    .'savings and credit association in which members save together regularly, buy shares, '
                    .'contribute to a welfare fund when configured, and can borrow from the group\'s own '
                    .'pooled resources. In FinancePro, each group belongs to a branch of an organization. '
                    .'Members are registered against a group, and their savings, share and welfare accounts '
                    .'and loans are recorded and tracked by the group. Borrowing is repaid from the group\'s '
                    .'pooled savings and is typically tied to the member\'s savings balance and eligibility rules.',
            ],
            [
                'title' => 'How do loans work in FinancePro?',
                'type' => AiKnowledgeDocumentType::LoanPolicy,
                'description' => 'Loan lifecycle: plans, eligibility, application, approval, disbursement, repayment.',
                'content' => 'Loans in FinancePro follow the configured loan plans. Staff create and activate '
                    .'loan plans that set repayment terms and interest behaviour. A member checks '
                    .'eligibility against a chosen plan for a requested amount and term; the eligibility '
                    .'checks cover membership status, plan status, the amount and term ranges and the '
                    .'member\'s existing active loans. A member then submits a loan application. '
                    .'Applications can add guarantors and collateral, upload supporting '
                    .'documents, and move through the organization\'s approval levels before being approved '
                    .'or rejected. Approved applications become loans which staff disburse. Repayments are '
                    .'recorded against the loan through the member\'s schedule or by staff collecting on the '
                    .'member\'s behalf; posted records are tracked separately from reversed ones. FinancePro '
                    .'reports delinquency so groups can follow up overdue loans.',
            ],
            [
                'title' => 'How a member checks loan eligibility in FinancePro',
                'type' => AiKnowledgeDocumentType::Procedure,
                'description' => 'Where the eligibility check lives in the member portal and what it checks.',
                'content' => 'In the FinancePro member portal, use the Loans section of the sidebar. '
                    .'"My Loans" lists every loan plan that is active for your organization, together with '
                    .'your existing loan applications, your active loans and your completed loans. Open a '
                    .'plan with "View Details" to see the plan\'s requirements and an eligibility summary '
                    .'for that plan. A dedicated Eligibility Check page is available for each plan: it '
                    .'takes a requested amount and a term in months and shows whether you are eligible, '
                    .'listing each eligibility check and the reasons for any failure. '
                    .'Eligibility is evaluated by the system against your membership status, whether the '
                    .'plan is active, whether the requested amount falls inside the plan\'s minimum and '
                    .'maximum, whether the requested term falls inside the plan\'s minimum and maximum '
                    .'term, and how many loans you already have active. A member who already has an '
                    .'active loan must have repaid at least 85 percent of it. Eligibility does not '
                    .'depend on savings, shares or welfare balances. If you are eligible you continue to '
                    .'the application form for that plan, where you enter the requested amount, the term, '
                    .'the loan purpose and a description of the purpose, then save the application as a '
                    .'draft. From your Loan Applications page you can add guarantors, add collateral and '
                    .'upload supporting documents, and then submit the application. Submitted '
                    .'applications move through your organization\'s approval levels before being approved '
                    .'or rejected.',
            ],
            [
                'title' => 'What determines FinancePro loan eligibility',
                'type' => AiKnowledgeDocumentType::LoanPolicy,
                'description' => 'The eligibility checks FinancePro actually performs.',
                'content' => 'FinancePro evaluates loan eligibility with five checks and no others. '
                    .'First, the member\'s membership status must be active. Second, the loan plan must be '
                    .'active. Third, the requested amount must fall within the plan\'s minimum and maximum '
                    .'amount. Fourth, the requested term in months must fall within the plan\'s minimum and '
                    .'maximum term. Fifth, the member must not exceed the plan\'s limit on active loans, '
                    .'and any loan the member already has active must be at least 85 percent repaid. '
                    .'Eligibility is not calculated from savings, share or welfare balances, and it is not '
                    .'affected by whether a previous application is still in draft, submitted or under '
                    .'review. Guarantor and collateral requirements are separate from eligibility: they '
                    .'are set by the loan plan and are collected while preparing an application.',
            ],
            [
                'title' => 'Savings, shares and welfare accounts',
                'type' => AiKnowledgeDocumentType::MemberHandbook,
                'description' => 'The member-account modules in a group.',
                'content' => 'Each member can hold savings, share and welfare accounts inside their VICOBA '
                    .'group. Savings accounts record deposits and withdrawals against the savings product '
                    .'the group configures. Share accounts record share purchases and redemptions over time. '
                    .'Welfare funds let members contribute to a configured welfare fund and request benefits '
                    .'when eligible. Every transaction is traceable, and transactions can be reversed with '
                    .'an audit trail. Members can view balances, share and welfare positions, statements and '
                    .'transaction receipts and make approved transactions from the member portal.',
            ],
            [
                'title' => 'Getting started with FinancePro',
                'type' => AiKnowledgeDocumentType::Procedure,
                'description' => 'Where a new organization or member begins.',
                'content' => 'A new organization registers through the website\'s registration form, and the '
                    .'FinancePro team activates it. A Super Administrator account is created during setup. '
                    .'From there, administrators create branches and VICOBA groups, configure savings, share '
                    .'and welfare products and payment methods, define loan plans and approval levels, and '
                    .'assign roles and permissions to staff. Members are registered into a group and can then '
                    .'open savings, share and welfare accounts, apply for loans, and sign in to the member '
                    .'portal. Support is available through the contact page.',
            ],
            [
                'title' => 'FinancePro contact and support',
                'type' => AiKnowledgeDocumentType::Faq,
                'description' => 'How to reach the FinancePro team.',
                'content' => 'For questions about registering an organization, activating an account, or help '
                    .'using FinancePro, contact the FinancePro team through the contact page on the website '
                    .'or by email at info@financepro.co.tz. The public AI assistant on this page answers '
                    .'general questions about FinancePro, VICOBA practice and the platform\'s features, but '
                    .'it has no access to any account, member or organization data.',
            ],
        ];
    }

    public function run(): void
    {
        $creator = User::role('Super Administrator')->first()
            ?? User::query()->first();

        if (! $creator) {
            $this->command?->warn('AiPublicKnowledgeSeeder skipped: no user exists to author the documents.');

            return;
        }

        // Documents are stored as drafts and processed by a queued job; force the
        // queue to sync so the seeded documents are embedded and active now.
        config(['queue.default' => 'sync']);

        $ingestion = app(AiKnowledgeIngestionService::class);

        foreach ($this->documents() as $document) {
            $ingestion->store(
                user: $creator,
                title: $document['title'],
                type: $document['type'],
                scope: AiKnowledgeScope::Public,
                content: $document['content'],
                description: $document['description'],
                source: 'public-knowledge',
            );
        }
    }
}
