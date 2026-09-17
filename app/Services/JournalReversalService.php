<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class JournalReversalService
{
    public function __construct(
        protected JournalPostingService $postingService,
        protected JournalNumberGenerator $numberGenerator,
        protected AuditService $auditService,
    ) {}

    public function reverseJournal(JournalEntry $original, string $reason, ?int $userId = null): JournalEntry
    {
        if ($original->status !== JournalEntryStatus::Posted) {
            throw new \InvalidArgumentException('Only posted journal entries can be reversed.');
        }

        if ($original->reversed_at) {
            throw new \InvalidArgumentException('This journal entry has already been reversed.');
        }

        if (!$original->accountingPeriod->isOpen()) {
            throw new \InvalidArgumentException('Cannot reverse in a closed accounting period.');
        }

        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user && !$user->organizations()->where('organizations.id', $original->organization_id)->exists()) {
                throw new \InvalidArgumentException('Cannot reverse journal from another organization.');
            }
        }

        $totalDebit = (float) $original->lines->sum('debit');
        $totalCredit = (float) $original->lines->sum('credit');

        return DB::transaction(function () use ($original, $reason, $userId, $totalDebit, $totalCredit) {
            $reversalNumber = $this->numberGenerator->generate();

            $reversalEntry = JournalEntry::create([
                'organization_id' => $original->organization_id,
                'branch_id' => $original->branch_id,
                'journal_number' => $reversalNumber,
                'accounting_period_id' => $original->accounting_period_id,
                'entry_date' => now()->toDateString(),
                'description' => "Reversal of {$original->journal_number}: {$reason}",
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'status' => JournalEntryStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $userId,
                'created_by' => $userId,
            ]);

            foreach ($original->lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'organization_id' => $original->organization_id,
                    'chart_of_account_id' => $line->chart_of_account_id,
                    'description' => "Reversal of: {$line->description}",
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                ]);
            }

            $original->update([
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
                'status' => JournalEntryStatus::Reversed,
            ]);

            $this->auditService->log(
                'journal_entry.reversed',
                $original,
                [],
                [
                    'original_number' => $original->journal_number,
                    'reversal_number' => $reversalNumber,
                    'reason' => $reason,
                ],
            );

            return $reversalEntry;
        });
    }
}
