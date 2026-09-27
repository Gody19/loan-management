<?php

namespace App\AI\Services;

use App\AI\Contracts\AiToolInterface;
use App\AI\Tools\AccountingSummaryTool;
use App\AI\Tools\CollateralRequirementTool;
use App\AI\Tools\CollectionSummaryTool;
use App\AI\Tools\DelinquencySummaryTool;
use App\AI\Tools\FinancialAnomalyTool;
use App\AI\Tools\FinancialTrendTool;
use App\AI\Tools\GuarantorEligibilityTool;
use App\AI\Tools\KnowledgeSearchTool;
use App\AI\Tools\LoanApplicationViewTool;
use App\AI\Tools\LoanEligibilityTool;
use App\AI\Tools\LoanRepaymentsTool;
use App\AI\Tools\LoanViewTool;
use App\AI\Tools\MemberFinancialSummaryTool;
use App\AI\Tools\MemberLoansTool;
use App\AI\Tools\MemberViewTool;
use App\AI\Tools\NullTool;
use App\AI\Tools\PortfolioSummaryTool;
use App\AI\Tools\PredictiveInsightTool;
use App\AI\Tools\SavingsSummaryTool;
use App\AI\Tools\ShareSummaryTool;
use App\AI\Tools\WelfareSummaryTool;

/**
 * Explicit registry of authorized AI capabilities.
 *
 * A capability is only usable if it is registered here with an explicit
 * permission gate, an explicit argument schema, and an explicit handler
 * class. There is no dynamic discovery: model output can never name a class,
 * method, SQL statement, or an unregistered capability and have it resolved.
 *
 * Phase 11.3 adds the first read-only business-data tools (member summary,
 * loan, repayment, application, and eligibility surfaces). Every business
 * tool is registered here with a permission gate and argument schema before
 * it becomes callable. Handlers are referenced by class-string constant —
 * never derived from input.
 */
class AiToolRegistry
{
    public const SCOPE_USER_ORG = 'user_org';

    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_PUBLIC = 'public';

    /**
     * @var array<string, array{permissions: string[], scope: string, arguments: array<string, string>, handler: class-string<AiToolInterface>, description: string}>
     */
    protected const CAPABILITIES = [
        'ai.conversation.list' => [
            'permissions' => ['ai.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => NullTool::class,
            'description' => 'List the conversations the user is allowed to see.',
        ],
        'ai.conversation.read' => [
            'permissions' => ['ai.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['conversation_id' => 'integer'],
            'handler' => NullTool::class,
            'description' => 'Read a conversation the user is allowed to see.',
        ],
        'ai.conversation.delete' => [
            'permissions' => ['ai.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['conversation_id' => 'integer'],
            'handler' => NullTool::class,
            'description' => 'Permanently delete a conversation the user owns.',
        ],
        'ai.chat' => [
            'permissions' => ['ai.use'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['conversation_id' => 'integer'],
            'handler' => NullTool::class,
            'description' => 'Continue an AI chat conversation.',
        ],
        'ai.member.view' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => MemberViewTool::class,
            'description' => 'Read a member profile summary within the authorized scope.',
        ],
        'ai.member.financial_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => MemberFinancialSummaryTool::class,
            'description' => 'Read the authoritative financial snapshot of a member.',
        ],
        'ai.member.savings_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => SavingsSummaryTool::class,
            'description' => 'Read savings account balances of a member.',
        ],
        'ai.member.share_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => ShareSummaryTool::class,
            'description' => 'Read share account holdings of a member.',
        ],
        'ai.member.welfare_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => WelfareSummaryTool::class,
            'description' => 'Read welfare account balances of a member.',
        ],
        'ai.member.loans' => [
            'permissions' => ['ai.loan.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string', 'limit' => 'integer'],
            'handler' => MemberLoansTool::class,
            'description' => 'List loans of a member within the authorized scope.',
        ],
        'ai.loan.view' => [
            'permissions' => ['ai.loan.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['loan_number' => 'string'],
            'handler' => LoanViewTool::class,
            'description' => 'Read a single loan record within the authorized scope.',
        ],
        'ai.loan.repayments' => [
            'permissions' => ['ai.loan-repayments.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['loan_number' => 'string', 'from' => 'string', 'to' => 'string', 'limit' => 'integer'],
            'handler' => LoanRepaymentsTool::class,
            'description' => 'Read repayment history of a loan, distinguishing posted from reversed.',
        ],
        'ai.loan.application.view' => [
            'permissions' => ['ai.loan-application.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['application_number' => 'string'],
            'handler' => LoanApplicationViewTool::class,
            'description' => 'Read a loan application within the authorized scope.',
        ],
        'ai.loan.eligibility.check' => [
            'permissions' => ['ai.loan-eligibility.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [
                'loan_plan_id' => 'integer',
                'requested_amount' => 'float',
                'term_months' => 'integer',
                'member_number' => 'string',
            ],
            'handler' => LoanEligibilityTool::class,
            'description' => 'Run the authoritative loan eligibility check for a member and plan.',
        ],
        'ai.guarantor.eligibility.check' => [
            'permissions' => ['ai.loan-application.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string', 'application_number' => 'string'],
            'handler' => GuarantorEligibilityTool::class,
            'description' => 'Check whether a member can guarantee a loan application.',
        ],
        'ai.collateral.requirement.check' => [
            'permissions' => ['ai.loan-application.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['loan_plan_id' => 'integer', 'requested_amount' => 'float', 'member_number' => 'string'],
            'handler' => CollateralRequirementTool::class,
            'description' => 'Read the collateral requirement for a loan plan and amount.',
        ],
        'ai.knowledge.search' => [
            'permissions' => ['ai.knowledge.search'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['search_term' => 'string', 'top_k' => 'integer'],
            'handler' => KnowledgeSearchTool::class,
            'description' => 'Search the approved FinancePro knowledge base (policies, procedures, handbooks, FAQs) within the authorized scope.',
        ],
        'ai.public.chat' => [
            'permissions' => [],
            'scope' => self::SCOPE_PUBLIC,
            'arguments' => ['message' => 'string'],
            'handler' => NullTool::class,
            'description' => 'Continue a public landing-page assistant conversation for an unauthenticated visitor.',
        ],
        'ai.public.knowledge.search' => [
            'permissions' => [],
            'scope' => self::SCOPE_PUBLIC,
            'arguments' => ['search_term' => 'string', 'top_k' => 'integer'],
            'handler' => NullTool::class,
            'description' => 'Search only the public FinancePro knowledge documents published to the landing-page assistant.',
        ],
        'ai.portfolio.view' => [
            'permissions' => ['ai.portfolio.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => PortfolioSummaryTool::class,
            'description' => 'Read the portfolio composition of the user\'s organizations: active loans, outstanding principal, status and plan mix, and near-term maturities.',
        ],
        'ai.delinquency.view' => [
            'permissions' => ['ai.delinquency.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => DelinquencySummaryTool::class,
            'description' => 'Read the portfolio-at-risk summary, aging buckets and delinquent loan profile of the user\'s organizations.',
        ],
        'ai.collection.view' => [
            'permissions' => ['ai.collection.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => CollectionSummaryTool::class,
            'description' => 'Read the collection position of the user\'s organizations: total due versus collected and reversal activity for the reporting month.',
        ],
        'ai.trend.view' => [
            'permissions' => ['ai.trend.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => FinancialTrendTool::class,
            'description' => 'Read the monthly disbursement and collection trend series for the user\'s organizations.',
        ],
        'ai.accounting.view' => [
            'permissions' => ['ai.accounting.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => AccountingSummaryTool::class,
            'description' => 'Read the accounting summary of the user\'s organizations: income, expenses, trial-balance integrity and liquid position from the authoritative reports.',
        ],
        'ai.anomaly.view' => [
            'permissions' => ['ai.anomaly.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => FinancialAnomalyTool::class,
            'description' => 'Run the deterministic financial-anomaly rules against the user\'s organizations and report the detected findings.',
        ],
        'ai.predictive.view' => [
            'permissions' => ['ai.predictive.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => PredictiveInsightTool::class,
            'description' => 'Read predictive intelligence for the user\'s organizations: portfolio, delinquency-risk, cash-flow and collection outlooks. Advisory statistical indications computed from historical FinancePro records, never guarantees.',
        ],
    ];

    public function registeredCapabilities(): array
    {
        return array_keys(self::CAPABILITIES);
    }

    public function businessCapabilities(): array
    {
        return array_values(array_filter(
            self::CAPABILITIES,
            fn (array $definition) => $definition['handler'] !== NullTool::class,
        ));
    }

    /**
     * Public capability allowlist exposed to the landing-page assistant. These
     * are the only capabilities an unauthenticated visitor can ever trigger;
     * everything else (all data tools and conversation management) stays
     * explicitly out of the public surface. They carry no permission gates —
     * there is no signed-in user to check — and all use the inert NullTool:
     * the public controller drives them directly through server-side services,
     * never through model-invoked tool execution.
     */
    public function publicCapabilities(): array
    {
        return array_values(array_filter(
            self::CAPABILITIES,
            fn (array $definition) => $definition['scope'] === self::SCOPE_PUBLIC
                && $definition['handler'] === NullTool::class,
        ));
    }

    public function has(string $capability): bool
    {
        return isset(self::CAPABILITIES[$capability]);
    }

    public function definition(string $capability): ?array
    {
        return self::CAPABILITIES[$capability] ?? null;
    }

    public function requiredPermissions(string $capability): array
    {
        return self::CAPABILITIES[$capability]['permissions'] ?? [];
    }

    public function argumentRules(string $capability): array
    {
        return self::CAPABILITIES[$capability]['arguments'] ?? [];
    }

    public function scope(string $capability): string
    {
        return self::CAPABILITIES[$capability]['scope'] ?? self::SCOPE_USER_ORG;
    }

    /**
     * @return class-string<AiToolInterface>|null
     */
    public function handler(string $capability): ?string
    {
        return self::CAPABILITIES[$capability]['handler'] ?? null;
    }

    public function description(string $capability): string
    {
        return self::CAPABILITIES[$capability]['description'] ?? '';
    }
}
