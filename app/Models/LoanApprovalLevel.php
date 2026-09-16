<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApprovalLevel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'minimum_amount', 'maximum_amount',
        'level', 'required_permission', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (LoanApprovalLevel $l) {
            if (!$l->created_by) $l->created_by = auth()->id();
        });
        static::updating(function (LoanApprovalLevel $l) {
            if (!$l->updated_by) $l->updated_by = auth()->id();
        });
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
    public function scopeForOrganization(Builder $query, int $organizationId): Builder { return $query->where('organization_id', $organizationId); }
}
