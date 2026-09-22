<?php

namespace App\Models;

use App\Enums\CollateralType;
use App\Enums\LoanCollateralStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanApplicationCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id', 'collateral_type', 'description',
        'estimated_value', 'reference_number', 'ownership_details',
        'notes', 'status', 'member_details',
        'reviewed_value', 'valuation_date', 'valuation_reference',
        'reviewed_by', 'reviewed_at', 'review_notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'reviewed_value' => 'decimal:2',
            'collateral_type' => CollateralType::class,
            'status' => LoanCollateralStatus::class,
            'valuation_date' => 'date',
            'reviewed_at' => 'datetime',
            'member_details' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (LoanApplicationCollateral $c) {
            if (!$c->created_by) $c->created_by = auth()->id();
        });
        static::updating(function (LoanApplicationCollateral $c) {
            if (!$c->updated_by) $c->updated_by = auth()->id();
        });
    }

    public function application(): BelongsTo { return $this->belongsTo(LoanApplication::class, 'loan_application_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function documents(): HasMany { return $this->hasMany(CollateralDocument::class, 'loan_application_collateral_id'); }

    public function getEffectiveValue(): float
    {
        return $this->reviewed_value !== null ? (float) $this->reviewed_value : (float) $this->estimated_value;
    }

    public function scopePending($query)
    {
        return $query->where('status', LoanCollateralStatus::Pending);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', LoanCollateralStatus::Verified);
    }

    public function scopeForApplication($query, int $applicationId)
    {
        return $query->where('loan_application_id', $applicationId);
    }
}
