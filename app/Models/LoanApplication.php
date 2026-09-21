<?php

namespace App\Models;

use App\Enums\LoanApplicationStatus;
use App\Enums\LoanPurpose;
use App\Enums\RepaymentFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'branch_id', 'vicoba_group_id', 'member_id', 'loan_plan_id',
        'application_number', 'requested_amount', 'requested_term', 'repayment_frequency',
        'loan_purpose', 'purpose_description', 'application_date', 'status',
        'eligibility_snapshot', 'eligibility_checked_at',
        'submitted_at', 'submitted_by',
        'rejected_at', 'rejected_by', 'rejection_reason',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'requested_term' => 'integer',
            'repayment_frequency' => RepaymentFrequency::class,
            'loan_purpose' => LoanPurpose::class,
            'application_date' => 'date',
            'status' => LoanApplicationStatus::class,
            'eligibility_snapshot' => 'array',
            'eligibility_checked_at' => 'datetime',
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (LoanApplication $app) {
            if (!$app->created_by) $app->created_by = auth()->id();
        });
        static::updating(function (LoanApplication $app) {
            if (!$app->updated_by) $app->updated_by = auth()->id();
        });
    }

    // Relationships
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function vicobaGroup(): BelongsTo { return $this->belongsTo(VicobaGroup::class, 'vicoba_group_id'); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function loanPlan(): BelongsTo { return $this->belongsTo(LoanPlan::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function rejector(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function guarantors(): HasMany { return $this->hasMany(LoanApplicationGuarantor::class); }
    public function collaterals(): HasMany { return $this->hasMany(LoanApplicationCollateral::class); }
    public function approvals(): HasMany { return $this->hasMany(LoanApplicationApproval::class); }
    public function loan(): HasOne { return $this->hasOne(Loan::class); }

    // Scopes
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('application_number', 'LIKE', "%{$search}%")
              ->orWhereHas('member', fn($mq) => $mq->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('member_number', 'LIKE', "%{$search}%"));
        });
    }
}
