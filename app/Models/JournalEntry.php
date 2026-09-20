<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    protected $fillable = [
        'organization_id', 'branch_id', 'journal_number', 'accounting_period_id',
        'entry_date', 'description', 'reference_type', 'reference_id',
        'source_type', 'source_id', 'status', 'posted_at', 'posted_by',
        'reversed_at', 'reversed_by', 'reversal_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date:Y-m-d',
            'status' => JournalEntryStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function accountingPeriod(): BelongsTo { return $this->belongsTo(AccountingPeriod::class); }
    public function poster(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }
    public function reverser(): BelongsTo { return $this->belongsTo(User::class, 'reversed_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(JournalLine::class, 'journal_entry_id'); }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePosted($query)
    {
        return $query->where('status', JournalEntryStatus::Posted);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', JournalEntryStatus::Draft);
    }

    public function scopeReversed($query)
    {
        return $query->where('status', JournalEntryStatus::Reversed);
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }
}
