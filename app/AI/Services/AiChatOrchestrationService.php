<?php

namespace App\AI\Services;

use App\AI\DTOs\AiChatToolPlan;
use App\AI\DTOs\AiContextData;
use App\Models\Member;

/**
 * Deterministic server-side orchestration for the normal chat flow.
 *
 * The Phase 11.4 chat UI posts only a plain question to POST /ai/chat. The
 * server — never the browser — decides whether an authorized registered
 * business capability should be consulted for that question.
 *
 * Rules:
 *  - The intent match is a small, explicit, keyword-driven table (no model
 *    output, no LLM "tool choice" round, no arbitrary tool routing).
 *  - Orchestration only happens for the acting user's OWN member scope
 *    (context->memberId). Staff without a linked member record and unknown or
 *    record-specific questions fall through to plain conversation.
 *  - Every argument is derived from the trusted context (member_number of the
 *    user's own member record; loan_number of that member's most recent loan) —
 *    never from message text, so a prompt cannot steer a handler at a target.
 *  - A plan is only returned when the user holds every registered permission
 *    of the capability; the AiChatToolPlan still re-checks the gate before the
 *    runner executes.
 *  - Questions that need values the server cannot derive safely (an eligibility
 *    requested amount, an arbitrary member/loan/application number for staff,
 *    guarantor/application references) are intentionally NOT orchestrated.
 *  - After the member scope, an organization-level intelligence plan (Phase
 *    11.7) is considered: a small keyword table maps org-level vocabulary
 *    (portfolio, PAR, delinquency, collections, trends, accounting, anomalies,
 *    insights) to the eight read-only ai.*.view capabilities. Tenants and
 *    scope are derived
 *    exclusively from the trusted context, and permission-gating still applies
 *    (VICOBA Members holding none of these capabilities fall through).
 *
 * This keeps the chat surface conversational while the AI, when a question is
 * orchestrated, answers from authoritative FinancePro data injected by
 * AiToolResultFormatter.
 */
class AiChatOrchestrationService
{
    private const LOAN_EXCLUSIONS = [
        'eligib', 'qualif', 'appl', 'plan', 'schedule',
        'repay', 'guarant', 'interest', 'collateral',
    ];

    /**
     * Capability => keyword patterns for organization-level financial
     * intelligence (Phase 11.7), predictive outlooks (Phase 11.8) and
     * proactive insights (Phase 11.9). Every
     * capability is permission-gated; the first matching capability wins.
     */
    private const ORGANIZATION_INTELLIGENCE = [
        'ai.delinquency.view' => [
            'portfolio at risk', 'delinquen', 'overdue', 'days past due', 'aging',
        ],
        'ai.portfolio.view' => [
            'portfolio', 'loan book', 'loan composition', 'loan mix', 'maturit',
        ],
        'ai.collection.view' => [
            'collect', 'recovery', 'arrear', 'repayment rate',
        ],
        'ai.trend.view' => [
            'trend', 'growth', 'monthly performance', 'over time', 'month over month',
            'month-on-month', 'last 3 months', 'last 6 months', 'last 12 months',
        ],
        'ai.accounting.view' => [
            'income statement', 'profit and loss', 'profit', 'loss', 'net income',
            'trial balance', 'balance sheet', 'accounting', 'expense', 'expenditure',
            'liquidity', 'cash position', 'financial statement',
        ],
        'ai.anomaly.view' => [
            'anomal', 'unusual', 'suspicious', 'red flag', 'irregular', 'alert',
        ],
        'ai.predictive.view' => [
            'forecast', 'predict', 'projection', 'outlook', 'what to expect',
        ],
        'ai.insights.view' => [
            'insight', 'proactive', 'action item', 'priorit', 'focus on',
        ],
    ];

    public function __construct(
        private readonly AiToolRegistry $registry,
    ) {}

    public function plan(AiContextData $context, string $message): ?AiChatToolPlan
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return null;
        }

        if ($context->memberId !== null) {
            $plan = $this->memberPlan($context, $text);

            if ($plan !== null) {
                return $plan;
            }
        }

        return $this->organizationPlan($context, $text);
    }

    /**
     * Record-specific planning for the acting user's own member scope.
     */
    protected function memberPlan(AiContextData $context, string $text): ?AiChatToolPlan
    {
        $member = Member::withTrashed()->find($context->memberId);

        if (! $member || $member->member_number === null || $member->member_number === '') {
            return null;
        }

        $memberNumber = (string) $member->member_number;

        // Repayment information — resolved to the member's most recent loan.
        if ($this->hasAny($text, ['repay'])) {
            $plan = $this->repaymentPlan($context, $member, $memberNumber);

            if ($plan !== null) {
                return $plan;
            }
        }

        if ($this->loanIntent($text)) {
            return $this->planFor(
                'ai.member.loans',
                'your loan information',
                ['member_number' => $memberNumber, 'limit' => 25],
                $context,
            );
        }

        if ($this->hasAny($text, ['financial summary', 'my finances', 'my money', 'my balance', 'my balances', 'my totals', 'my accounts'])) {
            return $this->planFor(
                'ai.member.financial_summary',
                'your financial summary',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        if ($this->hasAny($text, ['saving'])) {
            return $this->planFor(
                'ai.member.savings_summary',
                'your savings information',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        if (preg_match('/\bshares?\b/', $text) === 1) {
            return $this->planFor(
                'ai.member.share_summary',
                'your share information',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        if (str_contains($text, 'welfare')) {
            return $this->planFor(
                'ai.member.welfare_summary',
                'your welfare information',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        return null;
    }

    /**
     * Organization-level financial-intelligence planning (Phase 11.7). Scope
     * and tenant are never read from message text; capabilities take no
     * arguments and read everything from the trusted AiContextData. Users
     * without the capability's permission (e.g. VICOBA Members) fall through.
     */
    protected function organizationPlan(AiContextData $context, string $text): ?AiChatToolPlan
    {
        $labels = [
            'ai.portfolio.view' => 'organization portfolio summary',
            'ai.delinquency.view' => 'portfolio-at-risk and delinquency summary',
            'ai.collection.view' => 'collection summary',
            'ai.trend.view' => 'trend series',
            'ai.accounting.view' => 'accounting summary',
            'ai.anomaly.view' => 'anomaly findings',
            'ai.predictive.view' => 'predictive intelligence outlook',
            'ai.insights.view' => 'proactive insights and alerts',
        ];

        foreach (self::ORGANIZATION_INTELLIGENCE as $capability => $keywords) {
            if ($this->hasAny($text, $keywords)) {
                return $this->planFor(
                    $capability,
                    $labels[$capability],
                    [],
                    $context,
                );
            }
        }

        return null;
    }

    /**
     * Member repayment question: use the member's most recent loan number. A
     * loan that does not exist (or repayment capability without permission)
     * falls through to plain chat rather than failing the conversation.
     */
    protected function repaymentPlan(AiContextData $context, Member $member, string $memberNumber): ?AiChatToolPlan
    {
        $loan = $member->loans()->orderByDesc('id')->first();

        if (! $loan || $loan->loan_number === null || $loan->loan_number === '') {
            return null;
        }

        return $this->planFor(
            'ai.loan.repayments',
            'your recent repayment information',
            ['loan_number' => (string) $loan->loan_number, 'limit' => 50],
            $context,
        );
    }

    /**
     * "loan" questions that the orchestrator can answer from the member's own
     * loans (balance/status/information). Application, eligibility, schedule,
     * repayment, guarantor, interest and collateral questions are left to the
     * conversation engine — those need record references or values that must
     * never be guessed.
     */
    protected function loanIntent(string $text): bool
    {
        if (str_contains($text, 'loan')) {
            // Organization-level intelligence phrases (Phase 11.7) are not a
            // member "loan information" request — "loan portfolio" and
            // "portfolio at risk" must reach the org planning stage.
            if ($this->organizationLanguage($text)) {
                return false;
            }

            foreach (self::LOAN_EXCLUSIONS as $exclusion) {
                if (str_contains($text, $exclusion)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Whether the text speaks organization-level intelligence vocabulary.
     */
    protected function organizationLanguage(string $text): bool
    {
        foreach (self::ORGANIZATION_INTELLIGENCE as $keywords) {
            if ($this->hasAny($text, $keywords)) {
                return true;
            }
        }

        return false;
    }

    protected function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a plan only when the capability is registered and every required
     * permission is present in the trusted context. When $context is null the
     * permission check is skipped by the caller (repayment resolution already
     * guards scoping through the tool itself), but the returned plan is still
     * re-validated by AiChatToolPlan::permittedFor() before execution.
     */
    protected function planFor(string $capability, string $label, array $arguments, ?AiContextData $context): ?AiChatToolPlan
    {
        foreach ($this->registry->requiredPermissions($capability) as $permission) {
            if ($context !== null && ! $context->hasPermission($permission)) {
                return null;
            }
        }

        return new AiChatToolPlan($capability, $arguments, $label);
    }
}
