<?php

namespace App\AI\Services;

use App\AI\DTOs\AiChatToolPlan;
use App\AI\DTOs\AiContextData;
use App\Enums\AiQuestionType;
use App\Models\LoanPlan;
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
     * Loan application and eligibility questions. These are matched before
     * loanIntent(), which excludes them precisely because they need a plan and
     * an amount that must never be guessed.
     *
     * Phrases are deliberately kept whole rather than stemmed: hasAny() uses a
     * plain substring test, so a bare "appl" or "plan" would swallow unrelated
     * questions about payment plans and planning reports.
     */
    private const LOAN_APPLICATION_INTENT = [
        'am i eligible', 'i am eligible', 'eligible for', 'my eligibility',
        'loan eligibility', 'can i qualify', 'do i qualify', 'qualify for',
        'can i apply', 'how do i apply', 'can i get a loan', 'can i borrow',
        'apply for a loan', 'apply for another loan', 'apply for a new loan',
        'start a loan application', 'start an application',
        'begin a loan application', 'begin an application', 'take out a loan',
        // Kiswahili
        'nastahili', 'nimeastahili', 'inawezekana', 'naweza kuomba',
        'ninaweza kuomba', 'nomba mkopo', 'omba mkopo', 'kuomba mkopo',
        'mkopo mpya', 'ombe la mkopo', 'naweza kukopa', 'nikope',
    ];

    /**
     * Words that carry no discriminating power when matching a question against
     * a loan plan name. Without this, every plan would be called "Business Loan"
     * and no plan could ever be selected.
     */
    private const PLAN_NAME_STOPWORDS = ['loan', 'loans', 'plan', 'the', 'and', 'for', 'of', 'a'];

    /**
     * Statuses that mean an application exists but has not reached a decision.
     * Reported as context only — FinancePro has no rule that blocks a new
     * application on this basis.
     */
    private const IN_PROGRESS_APPLICATION_STATUSES = ['draft', 'submitted', 'under_review'];

    /**
     * Capability => keyword patterns for organization-level financial
     * intelligence (Phase 11.7), predictive outlooks (Phase 11.8) and
     * proactive insights (Phase 11.9). Every
     * capability is permission-gated; the first matching capability wins.
     */
    private const ORGANIZATION_INTELLIGENCE = [
        // Reporting is evaluated first and on purpose: asking for "the
        // collections report" or "the accounting report" is a request for a
        // report about that subject, not for a bare subject summary. Matching
        // the narrow subject keyword first would silently answer a report
        // request with an undated on-the-spot summary.
        'ai.reports.view' => [
            'report', 'intelligence report', 'management report', 'executive summary',
            'executive report', 'loan performance report', 'collections report',
            'cash flow report', 'cashflow report', 'operational report',
            'financial report', 'summarise the month', 'summarize the month',
            'board report', 'brief me', 'give me a report',
        ],
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
        'ai.accounting.income_statement' => [
            'income statement', 'statement of income', 'profit and loss',
            'profit & loss', 'p&l', 'revenue and expenditure',
            'suruhi ya mapato', 'mapato na matumizi',
        ],
        'ai.accounting.balance_sheet' => [
            'balance sheet', 'statement of financial position',
            'suruhi ya hali', 'hafidhau ya hali',
        ],
        'ai.accounting.trial_balance' => [
            'trial balance', 'faili ya majaribio', 'kumbukumbu ya hesabu',
        ],
        'ai.accounting.view' => [
            'accounting', 'expense', 'expenditure',
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

    /**
     * Report-type keywords for the Phase 12.0 reporting tool. The vocabulary is
     * mapped server-side to a closed enum; nothing is inferred from free text
     * beyond this table, and the reporting service validates it again.
     */
    private const REPORT_TYPE_KEYWORDS = [
        'accounting_intelligence' => ['accounting intelligence', 'accounting report', 'income statement report'],
        'cashflow_intelligence' => ['cash flow intelligence', 'cashflow intelligence', 'cash flow report', 'cashflow report'],
        'collections' => ['collections report', 'collection report', 'collections intelligence'],
        'loan_performance' => ['loan performance', 'loan portfolio report', 'lending report', 'loan book report'],
        'operational_intelligence' => ['operational report', 'operations report', 'operational intelligence', 'branch operations'],
    ];

    /**
     * Reporting-period keywords for the Phase 12.0 reporting tool. A period is
     * only ever chosen from these closed values; a free-text date is never
     * guessed (the reporting tool accepts an explicit custom range only from
     * validated request input).
     */
    private const PERIOD_KEYWORDS = [
        'today' => ['today'],
        'this_week' => ['this week'],
        'this_month' => ['this month', 'current month'],
        'this_quarter' => ['this quarter', 'current quarter'],
        'this_year' => ['this year', 'year to date', 'ytd'],
        'previous_quarter' => ['previous quarter', 'last quarter'],
        'previous_month' => ['previous month', 'last month'],
    ];

    /**
     * Platform-identity questions. These need no member or organization scope
     * and no financial record: the answer is the configured identity plus the
     * capabilities this caller is permitted to use, both read from the server.
     * They were previously unanswerable from any source and so reached the
     * model ungrounded, which improvised a general-assistant persona.
     *
     * @var string[]
     */
    private const IDENTITY = [
        'who are you', 'who is this', 'what are you', 'what is this',
        'your name', 'what is your name', 'introduce yourself', 'tell me about yourself',
        'what can you do', 'what can you help', 'how can you help',
        'what are you able to do', 'what do you do', 'your capabilities',
        'what can you tell me', 'what do you know', 'what data can you see',
        'what can you access',
        // Swahili
        'wewe ni nani', 'ni nani', 'jinsi ya kujitambulisha', 'jina lako',
        'unapaswa kusaidia', 'unaweza kusaidia na', 'unaweza kunisaidia',
        'wewe ni nini', 'unaweza kufanya nini',
    ];

    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly AiTerminologyService $terminology,
        private readonly AiIntentClassifier $classifier,
    ) {}

    public function plan(AiContextData $context, string $message): ?AiChatToolPlan
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return null;
        }

        // Identity is checked first and is independent of member/organization
        // scope: "who are you" must answer even when no member record is linked
        // and no organization-level intelligence permission is held.
        if ($this->hasAny($text, self::IDENTITY)) {
            return $this->planFor(
                'ai.identity.view',
                'the FinancePro Assistance identity and your permitted capabilities',
                [],
                $context,
            );
        }

        // A question about HOW FinancePro works, or about its documented rules,
        // is answered from approved knowledge and never from a business tool.
        // The classifier is the single source of truth for that decision: a tool
        // returns a record or a verdict, and neither can explain where the check
        // lives or which rules apply. Routing those questions to a tool is what
        // made the assistant tell a member that it could not retrieve eligibility
        // details when the member had only asked how to check them.
        if (in_array($this->classifier->classify($message), [
            AiQuestionType::FinanceProHowTo,
            AiQuestionType::FinanceProPolicy,
        ], true)) {
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

        // Loan application / eligibility questions are routed before loanIntent()
        // so they can reach a real capability instead of being excluded from
        // member loan information.
        $applicationPlan = $this->loanApplicationPlan($context, $member, $memberNumber, $text);

        if ($applicationPlan !== null) {
            return $applicationPlan;
        }

        // Repayment information — resolved to the member's most recent loan.
        if ($this->hasAny($text, ['repay', 'malipo', 'nili lipa', 'ulipo'])) {
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

        if ($this->hasAny($text, ['financial summary', 'my finances', 'my money', 'my balance', 'my balances', 'my totals', 'my accounts', 'muhtasari wa kifedha', 'fedha zangu', 'aka yangu kifedha'])) {
            return $this->planFor(
                'ai.member.financial_summary',
                'your financial summary',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        if ($this->hasAny($text, ['saving', 'akiba'])) {
            return $this->planFor(
                'ai.member.savings_summary',
                'your savings information',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        if ($this->hasAny($text, ['share', 'hisa'])) {
            return $this->planFor(
                'ai.member.share_summary',
                'your share information',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        if ($this->hasAny($text, ['welfare', 'wafadhili', 'kijamii'])) {
            return $this->planFor(
                'ai.member.welfare_summary',
                'your welfare information',
                ['member_number' => $memberNumber],
                $context,
            );
        }

        // Profile questions are evaluated last so a more specific financial
        // intent above always wins. ai.member.view was previously registered but
        // never planned, so a plain "what does my profile say" reached the model
        // with no authoritative context at all.
        if ($this->hasAny($text, ['my profile', 'my details', 'about me', 'my record', 'my membership', 'aka yangu', 'taarifa za aka'])) {
            return $this->planFor(
                'ai.member.view',
                'your member profile',
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
            'ai.accounting.income_statement' => 'the income statement',
            'ai.accounting.balance_sheet' => 'the balance sheet',
            'ai.accounting.trial_balance' => 'the trial balance',
            'ai.anomaly.view' => 'anomaly findings',
            'ai.predictive.view' => 'predictive intelligence outlook',
            'ai.insights.view' => 'proactive insights and alerts',
            'ai.reports.view' => 'management intelligence report',
        ];

        foreach (self::ORGANIZATION_INTELLIGENCE as $capability => $keywords) {
            if (! $this->hasAny($text, $keywords)) {
                continue;
            }

            // Phase 12.0: a report request carries a report type and a period,
            // both chosen from closed server-side tables. They are ordinary
            // (non-tenant) arguments and are re-gated by the tool policy.
            $arguments = $capability === 'ai.reports.view'
                ? [
                    'report_type' => $this->reportType($text),
                    'period' => $this->reportPeriod($text),
                ]
                : [];

            return $this->planFor(
                $capability,
                $labels[$capability],
                $arguments,
                $context,
            );
        }

        return null;
    }

    /**
     * The report type implied by the question, defaulting to the executive
     * portfolio report when the question names no specific report.
     */
    protected function reportType(string $text): string
    {
        foreach (self::REPORT_TYPE_KEYWORDS as $type => $keywords) {
            if ($this->hasAny($text, $keywords)) {
                return $type;
            }
        }

        return 'executive_portfolio';
    }

    protected function reportPeriod(string $text): string
    {
        foreach (self::PERIOD_KEYWORDS as $period => $keywords) {
            if ($this->hasAny($text, $keywords)) {
                return $period;
            }
        }

        return 'this_month';
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
     * Loan application workflow and eligibility questions.
     *
     * Two very different questions are separated here:
     *
     *  - "Can I apply for a loan?" is a workflow question. It is answered by
     *    ai.loan.application.start, which reports whether the member may begin
     *    the process and which plans are available.
     *  - "Am I eligible for a <plan> of <amount>?" is a calculation. Only when
     *    the question names one unambiguous plan AND one amount is
     *    ai.loan.eligibility.check invoked, with those exact values.
     *
     * If the plan is missing, ambiguous, or the amount is missing or ambiguous,
     * this falls back to the workflow capability so the assistant asks which
     * plan and amount apply. It never picks a plan or invents an amount.
     */
    protected function loanApplicationPlan(
        AiContextData $context,
        Member $member,
        string $memberNumber,
        string $text,
    ): ?AiChatToolPlan {
        if (! $this->hasAny($text, self::LOAN_APPLICATION_INTENT)) {
            return null;
        }

        $resolved = $this->resolveEligibilityArguments($member, $text);

        if ($resolved !== null) {
            return $this->planFor(
                'ai.loan.eligibility.check',
                'your authoritative loan eligibility result',
                [
                    'loan_plan_id' => $resolved['loan_plan_id'],
                    'requested_amount' => $resolved['requested_amount'],
                    'member_number' => $memberNumber,
                ],
                $context,
            );
        }

        return $this->planFor(
            'ai.loan.application.start',
            'whether you can begin a loan application, and the loan plans available to you',
            ['member_number' => $memberNumber],
            $context,
        );
    }

    /**
     * Extract an unambiguous (plan, amount) pair from the question, or null when
     * either is missing or ambiguous.
     *
     * The plan is matched on its real name within the member's own organization,
     * ignoring generic words. FinancePro has no notion of a default or primary
     * loan plan and LoanEligibilityService never reads the loan purpose, so a
     * plan can only be resolved by name the member actually used — and only
     * when exactly one active plan matches. Two matches means the member must
     * choose, not that the assistant picks one.
     *
     * @return array{loan_plan_id: int, requested_amount: float}|null
     */
    protected function resolveEligibilityArguments(Member $member, string $text): ?array
    {
        $amount = $this->terminology->requestedAmount($text);

        if ($amount === null) {
            return null;
        }

        $plans = LoanPlan::query()
            ->active()
            ->where('organization_id', $member->organization_id)
            ->orderBy('id')
            ->get()
            ->filter(fn (LoanPlan $plan) => $this->planIsNamed($text, $plan));

        if ($plans->count() !== 1) {
            return null;
        }

        return [
            'loan_plan_id' => (int) $plans->first()->id,
            'requested_amount' => $amount,
        ];
    }

    /**
     * True when the question names this plan. Every discriminating word of the
     * plan name must appear in the question; plans made up entirely of generic
     * words can never be matched, because that would make them ambiguous with
     * every other loan question.
     */
    protected function planIsNamed(string $text, LoanPlan $plan): bool
    {
        // Matched against both the original text and its terminology-normalized
        // form, so "mkopo wa biashara" selects the plan named "Business Loan".
        $haystacks = [$text, $this->terminology->normalize($text)];

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $plan->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $discriminating = array_values(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::PLAN_NAME_STOPWORDS, true),
        ));

        if ($discriminating === []) {
            return false;
        }

        foreach ($discriminating as $word) {
            $matched = false;

            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $word)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        return true;
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
        // "mkopo"/"mikopo" is the Swahili equivalent of "loan"; matching the
        // stem covers singular and plural without a language-specific branch.
        if (str_contains($text, 'loan') || str_contains($text, 'mkopo')) {
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
