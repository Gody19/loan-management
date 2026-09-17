<?php

namespace App\Services;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;

class AccountingPeriodService
{
    public function createPeriod(
        int $organizationId,
        string $name,
        string $startDate,
        string $endDate,
        ?int $userId = null,
    ): AccountingPeriod {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        if ($end->lte($start)) {
            throw new \InvalidArgumentException('End date must be after start date.');
        }

        $overlap = AccountingPeriod::forOrganization($organizationId)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            throw new \InvalidArgumentException('Accounting period overlaps with an existing period.');
        }

        return AccountingPeriod::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => AccountingPeriodStatus::Open,
        ]);
    }

    public function closePeriod(AccountingPeriod $period, ?int $userId = null): AccountingPeriod
    {
        if ($period->status === AccountingPeriodStatus::Closed) {
            throw new \InvalidArgumentException('Period is already closed.');
        }

        DB::transaction(function () use ($period, $userId) {
            $draftCount = $period->journalEntries()->where('status', 'draft')->count();
            if ($draftCount > 0) {
                throw new \InvalidArgumentException('Cannot close a period with draft journal entries. Post or delete all drafts first.');
            }

            $period->update([
                'status' => AccountingPeriodStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $userId,
            ]);
        });

        return $period->fresh();
    }

    public function getCurrentPeriod(int $organizationId): ?AccountingPeriod
    {
        return AccountingPeriod::forOrganization($organizationId)
            ->open()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    public function getPeriodForDate(int $organizationId, string $date): AccountingPeriod
    {
        $period = AccountingPeriod::forOrganization($organizationId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if (!$period) {
            throw new \InvalidArgumentException("No accounting period found for date: {$date}.");
        }

        return $period;
    }

    public function getPeriods(int $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return AccountingPeriod::forOrganization($organizationId)
            ->orderByDesc('start_date')
            ->get();
    }
}
