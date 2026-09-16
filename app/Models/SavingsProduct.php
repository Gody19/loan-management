<?php

namespace App\Models;

use App\Enums\SavingsAccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'minimum_amount',
        'maximum_amount',
        'minimum_balance',
        'allow_withdrawal',
        'withdrawal_limit',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'minimum_balance' => 'decimal:2',
            'withdrawal_limit' => 'decimal:2',
            'status' => SavingsAccountStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SavingsProduct $product) {
            if (! $product->created_by) {
                $product->created_by = auth()->id();
            }
        });

        static::updating(function (SavingsProduct $product) {
            if (! $product->updated_by) {
                $product->updated_by = auth()->id();
            }
        });
    }

    // Relationships

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class, 'savings_product_id');
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
