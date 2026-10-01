<?php

namespace App\Console\Commands;

use App\AI\ProactiveIntelligence\Services\ProactiveInsightService;
use App\Models\Organization;
use Illuminate\Console\Command;

/**
 * Runs the deterministic Proactive Intelligence generation pass (Phase 11.9)
 * for every organization (or a single one). Each pass re-detects the enabled
 * rules, idempotently refreshes existing open insights under their stable
 * dedup keys, retires insights whose condition stopped holding, and pushes
 * in-app notifications for brand-new insights. Re-running is safe — rows are
 * updated, never duplicated.
 */
class AiGenerateInsights extends Command
{
    protected $signature = 'ai:generate-insights {--organization= : Only generate insights for this organization id}';

    protected $description = 'Generate proactive financial intelligence insights and alerts for organizations';

    public function handle(ProactiveInsightService $insights): int
    {
        $query = Organization::query()->orderBy('id');

        if ($organizationId = $this->option('organization')) {
            $query->where('id', (int) $organizationId);
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->warn('No organizations to process.');

            return self::SUCCESS;
        }

        $summary = $insights->runForOrganizations($organizations->pluck('id')->all());

        $this->info(sprintf(
            'Proactive insights for %d organization(s): %d created, %d updated, %d expired, %d notifications.',
            $summary['organizations'],
            $summary['created'],
            $summary['updated'],
            $summary['expired'],
            $summary['notifications'],
        ));

        return self::SUCCESS;
    }
}
