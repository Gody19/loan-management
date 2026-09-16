<?php

namespace App\Models;

use App\Enums\InterestMethod;
use App\Enums\LoanPlanStatus;
use App\Enums\LoanPurpose;
use App\Enums\RepaymentFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'loan_purpose',
        'minimum_amount',
        'maximum_amount',
        'interest_rate',
        'interest_method',
        'minimum_term',
        'maximum_term',
        'repayment_frequency',
        'maximum_active_loans',
        'requires_guarantor',
        'minimum_guarantors',
        'requires_collateral',
        'minimum_savings_balance',
        'savings_multiplier',
        'share_multiplier',
        'maximum_loan_to_savings_ratio',
        'grace_period',
        'processing_fee',
        'insurance_fee',
        'late_payment_allowed',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'minimum_savings_balance' => 'decimal:2',
            'savings_multiplier' => 'decimal:2',
            'share_multiplier' => 'decimal:2',
            'maximum_loan_to_savings_ratio' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'loan_purpose' => LoanPurpose::class,
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'requires_guarantor' => 'boolean',
            'requires_collateral' => 'boolean',
            'late_payment_allowed' => 'boolean',
            'status' => LoanPlanStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (LoanPlan $plan) {
            if (! $plan->created_by) {
                $plan->created_by = auth()->id();
            }
        });

        static::updating(function (LoanPlan $plan) {
            if (! $plan->updated_by) {
                $plan->updated_by = auth()->id();
            }
        });
    }

    // Relationships

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LoanPlanStatus::Active);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
