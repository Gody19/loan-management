<?php

namespace Tests\Traits;

use App\Models\Organization;
use App\Models\User;
use App\Services\AccountingConfigurationService;
use App\Services\AccountingPeriodService;
use App\Services\ChartOfAccountsService;

trait HasAccountingSetup
{
    protected function setUpAccountingFor(User $user, Organization $organization, string $year = '2026'): void
    {
        $chartService = app(ChartOfAccountsService::class);
        $chartService->initializeDefaultChart($organization->id, $user->id);

        $configService = app(AccountingConfigurationService::class);
        $configService->initializeDefaultMappings($organization->id);

        $periodService = app(AccountingPeriodService::class);
        $periodService->createPeriod(
            $organization->id,
            "Test Period {$year}",
            "{$year}-01-01",
            "{$year}-12-31",
            $user->id,
        );
    }
}
