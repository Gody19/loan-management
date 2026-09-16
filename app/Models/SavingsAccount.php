<?php

namespace App\Models;

use App\Enums\SavingsAccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id',
        'organization_id',
        'branch_id',
        'vicoba_group_id',
        'savings_product_id',
        'account_number',
        'opening_date',
        'status',
        'current_balance',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'opening_date' => 'date',
            'status' => SavingsAccountStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SavingsAccount $account) {
            if (! $account->created_by) {
                $account->created_by = auth()->id();
            }
        });

        static::updating(function (SavingsAccount $account) {
            if (! $account->updated_by) {
                $account->updated_by = auth()->id();
            }
        });
    }

    // Relationships

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vicobaGroup(): BelongsTo
    {
        return $this->belongsTo(VicobaGroup::class, 'vicoba_group_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SavingsProduct::class, 'savings_product_id');
    }

    public function plan(): BelongsTo
    {
        return $this->product();
    }

    public function getSavingsProductAttribute()
    {
        return $this->product;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class, 'savings_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
