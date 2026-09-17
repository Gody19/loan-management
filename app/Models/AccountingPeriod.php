<?php

namespace App\Models;

use App\Enums\AccountingPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'start_date', 'end_date',
        'status', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AccountingPeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function journalEntries(): HasMany { return $this->hasMany(JournalEntry::class, 'accounting_period_id'); }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', AccountingPeriodStatus::Open);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', AccountingPeriodStatus::Closed);
    }

    public function isOpen(): bool
    {
        return $this->status === AccountingPeriodStatus::Open;
    }

    public function containsDate(\Carbon\CarbonInterface $date): bool
    {
        return $date->gte($this->start_date) && $date->lte($this->end_date);
    }
}
