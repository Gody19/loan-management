<?php

namespace App\Models;

use App\Enums\ShareAccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id',
        'organization_id',
        'branch_id',
        'vicoba_group_id',
        'share_product_id',
        'account_number',
        'total_shares',
        'total_value',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_shares' => 'integer',
            'total_value' => 'decimal:2',
            'status' => ShareAccountStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ShareAccount $account) {
            if (! $account->created_by) {
                $account->created_by = auth()->id();
            }
        });

        static::updating(function (ShareAccount $account) {
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
        return $this->belongsTo(ShareProduct::class, 'share_product_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ShareTransaction::class, 'share_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function generateAccountNumber(): string
    {
        $prefix = 'SHR-';
        $padding = 6;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($prefix, $padding) {
            $last = static::withoutGlobalScopes()
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(account_number, '.(strlen($prefix) + 1).') AS UNSIGNED) DESC')
                ->first();

            if ($last) {
                $lastNumber = (int) substr($last->account_number, strlen($prefix));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix.str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT);
        });
    }
}
