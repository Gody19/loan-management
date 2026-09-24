<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\LoanDelinquencyService;
use Illuminate\Console\Command;

class UpdateLoanDelinquency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:update-delinquency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark overdue loan installments for every organization';

    /**
     * Execute the console command.
     */
    public function handle(LoanDelinquencyService $delinquencyService): int
    {
        $organizationIds = Organization::query()->orderBy('id')->pluck('id');

        $totalUpdated = 0;

        foreach ($organizationIds as $organizationId) {
            $result = $delinquencyService->updateDelinquencyStatuses((int) $organizationId);
            $totalUpdated += (int) $result['loans_updated'];
        }

        $this->info(
            sprintf(
                'Checked %d organization(s). Marked %d loan installment(s) as overdue.',
                $organizationIds->count(),
                $totalUpdated
            )
        );

        return self::SUCCESS;
    }
}