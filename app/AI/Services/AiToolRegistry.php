<?php

namespace App\AI\Services;

use App\AI\Contracts\AiToolInterface;

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

    /**
     * @var array<string, array{permissions: string[], scope: string, arguments: array<string, string>, handler: class-string<AiToolInterface>, description: string}>
     */
    protected const CAPABILITIES = [
        'ai.conversation.list' => [
            'permissions' => ['ai.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => [],
            'handler' => \App\AI\Tools\NullTool::class,
            'description' => 'List the conversations the user is allowed to see.',
        ],
        'ai.conversation.read' => [
            'permissions' => ['ai.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['conversation_id' => 'integer'],
            'handler' => \App\AI\Tools\NullTool::class,
            'description' => 'Read a conversation the user is allowed to see.',
        ],
        'ai.chat' => [
            'permissions' => ['ai.use'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['conversation_id' => 'integer'],
            'handler' => \App\AI\Tools\NullTool::class,
            'description' => 'Continue an AI chat conversation.',
        ],
        'ai.member.view' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => \App\AI\Tools\MemberViewTool::class,
            'description' => 'Read a member profile summary within the authorized scope.',
        ],
        'ai.member.financial_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => \App\AI\Tools\MemberFinancialSummaryTool::class,
            'description' => 'Read the authoritative financial snapshot of a member.',
        ],
        'ai.member.savings_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => \App\AI\Tools\SavingsSummaryTool::class,
            'description' => 'Read savings account balances of a member.',
        ],
        'ai.member.share_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => \App\AI\Tools\ShareSummaryTool::class,
            'description' => 'Read share account holdings of a member.',
        ],
        'ai.member.welfare_summary' => [
            'permissions' => ['ai.member.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string'],
            'handler' => \App\AI\Tools\WelfareSummaryTool::class,
            'description' => 'Read welfare account balances of a member.',
        ],
        'ai.member.loans' => [
            'permissions' => ['ai.loan.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string', 'limit' => 'integer'],
            'handler' => \App\AI\Tools\MemberLoansTool::class,
            'description' => 'List loans of a member within the authorized scope.',
        ],
        'ai.loan.view' => [
            'permissions' => ['ai.loan.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['loan_number' => 'string'],
            'handler' => \App\AI\Tools\LoanViewTool::class,
            'description' => 'Read a single loan record within the authorized scope.',
        ],
        'ai.loan.repayments' => [
            'permissions' => ['ai.loan-repayments.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['loan_number' => 'string', 'from' => 'string', 'to' => 'string', 'limit' => 'integer'],
            'handler' => \App\AI\Tools\LoanRepaymentsTool::class,
            'description' => 'Read repayment history of a loan, distinguishing posted from reversed.',
        ],
        'ai.loan.application.view' => [
            'permissions' => ['ai.loan-application.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['application_number' => 'string'],
            'handler' => \App\AI\Tools\LoanApplicationViewTool::class,
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
            'handler' => \App\AI\Tools\LoanEligibilityTool::class,
            'description' => 'Run the authoritative loan eligibility check for a member and plan.',
        ],
        'ai.guarantor.eligibility.check' => [
            'permissions' => ['ai.loan-application.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['member_number' => 'string', 'application_number' => 'string'],
            'handler' => \App\AI\Tools\GuarantorEligibilityTool::class,
            'description' => 'Check whether a member can guarantee a loan application.',
        ],
        'ai.collateral.requirement.check' => [
            'permissions' => ['ai.loan-application.view'],
            'scope' => self::SCOPE_USER_ORG,
            'arguments' => ['loan_plan_id' => 'integer', 'requested_amount' => 'float', 'member_number' => 'string'],
            'handler' => \App\AI\Tools\CollateralRequirementTool::class,
            'description' => 'Read the collateral requirement for a loan plan and amount.',
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
            fn (array $definition) => $definition['handler'] !== \App\AI\Tools\NullTool::class,
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