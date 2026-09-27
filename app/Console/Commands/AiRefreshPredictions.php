<?php

namespace App\Console\Commands;

use App\AI\PredictiveIntelligence\Services\PredictiveIntelligenceService;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use App\Models\Organization;
use Illuminate\Console\Command;

/**
 * Refreshes the statistical predictive-intelligence snapshots for every
 * organization (or a single one). The dashboard and AI tool already generate
 * lazily; this command produces a scheduled, audited daily snapshot so
 * outlooks are always warm. Re-running within one data snapshot is
 * idempotent — the same row is updated, never duplicated.
 */
class AiRefreshPredictions extends Command
{
    protected $signature = 'ai:refresh-predictions {--organization= : Only refresh predictions for this organization id}';

    protected $description = 'Refresh statistical predictive intelligence snapshots for organizations';

    public function handle(PredictiveIntelligenceService $predictive): int
    {
        $query = Organization::query()->orderBy('id');

        if ($organizationId = $this->option('organization')) {
            $query->where('id', (int) $organizationId);
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->warn('No organizations to refresh.');

            return self::SUCCESS;
        }

        $generated = 0;
        $insufficient = 0;

        foreach ($organizations as $organization) {
            foreach (PredictiveInsightType::cases() as $type) {
                $prediction = $predictive->generate($type, (int) $organization->id, [], null);

                if ($prediction->status === PredictiveInsightStatus::Generated) {
                    $generated++;
                } else {
                    $insufficient++;
                }
            }
        }

        $this->info("Refreshed predictions for {$organizations->count()} organization(s): {$generated} generated, {$insufficient} insufficient-data.");

        return self::SUCCESS;
    }
}
