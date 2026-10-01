<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\ProactiveIntelligence\Services\ProactiveInsightService;
use App\Enums\ProactiveInsightStatus;
use App\Models\AiInsight;
use App\Models\User;

/**
 * ai.insights.view — proactive insights and alerts for the acting user's own
 * organizations/branches. No arguments are accepted: tenant/branch scope is
 * derived exclusively from the trusted context and the payload is strictly
 * aggregate with member-level identity only where the rule scoped to a record
 * (e.g. a delinquent loan) that the user already holds permission to read.
 * The assistant reads open insights only; acknowledge/resolve/dismiss remain
 * human-only dashboard actions gated by the same capability.
 */
class ProactiveInsightsTool implements AiToolInterface
{
    public function __construct(
        private readonly ProactiveInsightService $insights,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $open = $this->insights->forDashboard($context);

        return [
            'as_of' => now()->toISOString(),
            'open_count' => $open->count(),
            'insights' => $open->map(fn (AiInsight $insight) => $this->serialize($insight))->values()->all(),
            'note' => 'Deterministic rule-based advisories computed from FinancePro records; they are not guarantees, and acknowledging, resolving or dismissing an insight is always a human action on the dashboard.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(AiInsight $insight): array
    {
        return [
            'id' => $insight->id,
            'organization_id' => $insight->organization_id,
            'branch_id' => $insight->branch_id,
            'type' => $insight->type->value,
            'type_label' => $insight->type->label(),
            'severity' => $insight->severity->value,
            'severity_label' => $insight->severity->label(),
            'status' => $insight->status->value,
            'status_label' => $insight->status->label(),
            'title' => $insight->title,
            'summary' => $insight->summary,
            'recommendation' => $insight->recommendation,
            'source' => [
                'type' => $insight->source_type,
                'id' => $insight->source_id,
            ],
            'period' => [
                'from' => $insight->period_start?->toDateString(),
                'through' => $insight->period_end?->toDateString(),
            ],
            'data_through' => $insight->data_through?->toDateString(),
            'generated_at' => $insight->generated_at?->toISOString(),
            'open' => $insight->status !== ProactiveInsightStatus::Dismissed,
        ];
    }
}
