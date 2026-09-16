<?php

namespace App\Models;

use App\Enums\SavingsAccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WelfareFund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'code', 'description',
        'contribution_type', 'default_amount', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'status' => SavingsAccountStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (WelfareFund $m) {
            if (! $m->created_by) {
                $m->created_by = auth()->id();
            }
        });
        static::updating(function (WelfareFund $m) {
            if (! $m->updated_by) {
                $m->updated_by = auth()->id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(WelfareAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SavingsAccountStatus::Active);
    }
}
