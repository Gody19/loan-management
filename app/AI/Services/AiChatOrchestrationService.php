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

    public function __construct(
        private readonly AiToolRegistry $registry,
    ) {}

    public function plan(AiContextData $context, string $message): ?AiChatToolPlan
    {
        if ($context->memberId === null) {
            return null;
        }

        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return null;
        }

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
            foreach (self::LOAN_EXCLUSIONS as $exclusion) {
                if (str_contains($text, $exclusion)) {
                    return false;
                }
            }

            return true;
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