<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class JournalNumberGenerator
{
    public function generate(): string
    {
        $prefix = 'JE-' . date('Y') . '-';
        $lastEntry = JournalEntry::where('journal_number', 'like', $prefix . '%')
            ->orderByDesc('journal_number')
            ->first();

        if ($lastEntry) {
            $lastNumber = (int) substr($lastEntry->journal_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
