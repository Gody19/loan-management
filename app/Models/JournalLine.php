<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'organization_id', 'chart_of_account_id',
        'description', 'debit', 'credit',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id'); }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('chart_of_account_id', $accountId);
    }
}
