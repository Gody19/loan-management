<?php

namespace App\Models;

use App\Enums\WelfareAccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WelfareAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id', 'organization_id', 'branch_id', 'vicoba_group_id',
        'welfare_fund_id', 'account_number', 'current_balance', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'status' => WelfareAccountStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (WelfareAccount $m) {
            if (! $m->created_by) {
                $m->created_by = auth()->id();
            }
        });
        static::updating(function (WelfareAccount $m) {
            if (! $m->updated_by) {
                $m->updated_by = auth()->id();
            }
        });
    }

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

    public function fund(): BelongsTo
    {
        return $this->belongsTo(WelfareFund::class, 'welfare_fund_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WelfareTransaction::class, 'welfare_account_id');
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
