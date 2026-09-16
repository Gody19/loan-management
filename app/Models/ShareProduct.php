<?php

namespace App\Models;

use App\Enums\SavingsAccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'share_price',
        'minimum_shares',
        'maximum_shares',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'share_price' => 'decimal:2',
            'minimum_shares' => 'integer',
            'maximum_shares' => 'integer',
            'status' => SavingsAccountStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ShareProduct $product) {
            if (! $product->created_by) {
                $product->created_by = auth()->id();
            }
        });

        static::updating(function (ShareProduct $product) {
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
        return $this->hasMany(ShareAccount::class, 'share_product_id');
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
