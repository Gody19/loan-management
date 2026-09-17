<?php

namespace App\Models;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'branch_id', 'member_id', 'loan_plan_id',
        'loan_application_id', 'disbursed_by',
        'loan_number', 'principal_amount', 'disbursed_amount',
        'interest_rate', 'interest_method', 'term_months', 'repayment_frequency',
        'total_interest', 'total_amount', 'processing_fee', 'insurance_fee',
        'amount_paid', 'outstanding_balance',
        'grace_period', 'status', 'disbursement_date', 'maturity_date',
        'next_payment_date', 'installments_paid', 'total_installments',
        'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'term_months' => 'integer',
            'total_interest' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'status' => LoanStatus::class,
            'disbursement_date' => 'date',
            'maturity_date' => 'date',
            'next_payment_date' => 'date',
            'installments_paid' => 'integer',
            'total_installments' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Loan $loan) {
            if (!$loan->created_by) $loan->created_by = auth()->id();
        });
        static::updating(function (Loan $loan) {
            if (!$loan->updated_by) $loan->updated_by = auth()->id();
        });
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function loanPlan(): BelongsTo { return $this->belongsTo(LoanPlan::class); }
    public function loanApplication(): BelongsTo { return $this->belongsTo(LoanApplication::class); }
    public function disburser(): BelongsTo { return $this->belongsTo(User::class, 'disbursed_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function repaymentSchedule(): HasMany
    {
        return $this->hasMany(LoanRepaymentSchedule::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(LoanDisbursement::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function latestDisbursement(): HasOne
    {
        return $this->hasOne(LoanDisbursement::class)->latestOfMany();
    }

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
            $q->where('loan_number', 'LIKE', "%{$search}%")
              ->orWhereHas('member', fn($mq) => $mq->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('member_number', 'LIKE', "%{$search}%"));
        });
    }

    public function getLoanPlanAttribute(): LoanPlan
    {
        return $this->loanPlan()->first() ?? new LoanPlan();
    }
}
