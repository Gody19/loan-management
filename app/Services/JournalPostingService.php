<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class JournalPostingService
{
    public function __construct(
        protected JournalNumberGenerator $numberGenerator,
        protected AuditService $auditService,
    ) {}

    public function createDraft(array $data, ?int $userId = null): JournalEntry
    {
        $this->validateLineData($data['lines'] ?? []);

        $period = $this->resolvePeriod($data['organization_id'], $data['entry_date']);
        $this->validateAccountOwnership($data['organization_id'], $data['lines'] ?? []);

        $totalDebit = collect($data['lines'])->sum('debit');
        $totalCredit = collect($data['lines'])->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \InvalidArgumentException(
                "Journal entry is not balanced. Debit: {$totalDebit}, Credit: {$totalCredit}."
            );
        }

        if ($totalDebit == 0 && $totalCredit == 0) {
            throw new \InvalidArgumentException('Journal entry must have at least one non-zero amount.');
        }

        $journalNumber = $this->numberGenerator->generate();

        return DB::transaction(function () use ($data, $journalNumber, $period, $totalDebit, $totalCredit, $userId) {
            $entry = JournalEntry::create([
                'organization_id' => $data['organization_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'journal_number' => $journalNumber,
                'accounting_period_id' => $period->id,
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => JournalEntryStatus::Draft,
                'created_by' => $userId,
            ]);

            foreach ($data['lines'] as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'organization_id' => $data['organization_id'],
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => round($line['debit'] ?? 0, 2),
                    'credit' => round($line['credit'] ?? 0, 2),
                ]);
            }

            $this->auditService->log(
                'journal_entry.created',
                $entry,
                [],
                ['journal_number' => $journalNumber, 'total' => $totalDebit],
            );

            return $entry;
        });
    }

    public function postEntry(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        if ($entry->status !== JournalEntryStatus::Draft) {
            throw new \InvalidArgumentException('Only draft journal entries can be posted.');
        }

        if (!$entry->accountingPeriod->isOpen()) {
            throw new \InvalidArgumentException('Cannot post to a closed accounting period.');
        }

        $totalDebit = (float) $entry->lines->sum('debit');
        $totalCredit = (float) $entry->lines->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \InvalidArgumentException(
                "Journal entry is not balanced. Debit: {$totalDebit}, Credit: {$totalCredit}."
            );
        }

        if ($entry->lines->count() < 2) {
            throw new \InvalidArgumentException('Journal entry must have at least two lines.');
        }

        foreach ($entry->lines as $line) {
            if ($line->debit < 0 || $line->credit < 0) {
                throw new \InvalidArgumentException('Debit and credit amounts must not be negative.');
            }
            if ($line->debit > 0 && $line->credit > 0) {
                throw new \InvalidArgumentException('A line cannot have both debit and credit amounts.');
            }
            if ($line->debit == 0 && $line->credit == 0) {
                throw new \InvalidArgumentException('A line must have either a debit or credit amount.');
            }
        }

        DB::transaction(function () use ($entry, $userId, $totalDebit) {
            $entry->update([
                'status' => JournalEntryStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $userId,
            ]);

            $this->auditService->log(
                'journal_entry.posted',
                $entry,
                [],
                ['journal_number' => $entry->journal_number, 'total' => $totalDebit],
            );
        });

        return $entry->fresh(['lines']);
    }

    public function createAndPost(array $data, ?int $userId = null): JournalEntry
    {
        $entry = $this->createDraft($data, $userId);
        return $this->postEntry($entry, $userId);
    }

    public function getSourceJournal(string $sourceType, int $sourceId): ?JournalEntry
    {
        $entries = JournalEntry::where('source_id', $sourceId)
            ->where('status', '!=', JournalEntryStatus::Reversed)
            ->get();

        return $entries->first(fn ($e) => $e->source_type === $sourceType);
    }

    public function hasPostedJournal(string $sourceType, int $sourceId): bool
    {
        return $this->getSourceJournal($sourceType, $sourceId) !== null;
    }

    public function getEntry(int $organizationId, int $entryId): JournalEntry
    {
        $entry = JournalEntry::where('id', $entryId)
            ->where('organization_id', $organizationId)
            ->with('lines.account')
            ->first();

        if (!$entry) {
            throw new \InvalidArgumentException('Journal entry not found or does not belong to this organization.');
        }

        return $entry;
    }

    public function getEntries(
        int $organizationId,
        ?string $status = null,
        ?int $branchId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $periodId = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = JournalEntry::forOrganization($organizationId)->with('lines.account');

        if ($status) {
            $query->where('status', $status);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        if ($periodId) {
            $query->where('accounting_period_id', $periodId);
        }

        return $query->orderByDesc('entry_date')->orderByDesc('id');
    }

    protected function validateLineData(array $lines): void
    {
        if (empty($lines)) {
            throw new \InvalidArgumentException('Journal entry must have at least one line.');
        }

        foreach ($lines as $index => $line) {
            if (!isset($line['chart_of_account_id'])) {
                throw new \InvalidArgumentException("Line " . ($index + 1) . " is missing chart_of_account_id.");
            }
            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;
            if ($debit < 0 || $credit < 0) {
                throw new \InvalidArgumentException("Line " . ($index + 1) . " has negative amount.");
            }
            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException("Line " . ($index + 1) . " has both debit and credit.");
            }
            if ($debit == 0 && $credit == 0) {
                throw new \InvalidArgumentException("Line " . ($index + 1) . " has zero amount.");
            }
        }
    }

    protected function validateAccountOwnership(int $organizationId, array $lines): void
    {
        $accountIds = array_column($lines, 'chart_of_account_id');
        $ownedCount = ChartOfAccount::where('organization_id', $organizationId)
            ->whereIn('id', $accountIds)
            ->count();

        if ($ownedCount !== count($accountIds)) {
            throw new \InvalidArgumentException('One or more accounts do not belong to this organization.');
        }
    }

    protected function resolvePeriod(int $organizationId, string $date): AccountingPeriod
    {
        $period = AccountingPeriod::forOrganization($organizationId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if (!$period) {
            throw new \InvalidArgumentException("No open accounting period for date: {$date}.");
        }

        return $period;
    }
}
