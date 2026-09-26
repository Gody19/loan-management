<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\FinancialAnomalyDetectionService;
use App\Models\User;

/**
 * ai.anomaly.view — run the deterministic financial-anomaly rules against the
 * acting user's organizations and report the (bounded) findings. Detection is
 * read-only with a day-keyed review trail.
 */
class FinancialAnomalyTool implements AiToolInterface
{
    public function __construct(
        private readonly FinancialAnomalyDetectionService $detection,
    ) {}

    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        $findings = $this->detection->detect($context->organizationIds);

        return [
            'findings_count' => count($findings),
            'findings' => array_map(fn ($finding) => $finding->toArray(), $findings),
        ];
    }
}
