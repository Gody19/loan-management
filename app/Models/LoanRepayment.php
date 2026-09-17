<?php

namespace App\Models;

use App\Enums\LoanRepaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'organization_id', 'branch_id', 'member_id',
        'payment_method_id', 'received_by',
        'repayment_number', 'amount', 'principal_portion', 'interest_portion',
        'fee_portion', 'overpayment_amount', 'payment_date', 'payment_method',
        'reference_number', 'status', 'reversal_reason', 'reversed_by',
        'reversal_date', 'idempotency_key', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_portion' => 'decimal:2',
            'interest_portion' => 'decimal:2',
            'fee_portion' => 'decimal:2',
            'overpayment_amount' => 'decimal:2',
            'payment_date' => 'date',
            'reversal_date' => 'date',
            'status' => LoanRepaymentStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (LoanRepayment $repayment) {
            if (!$repayment->received_by) {
                $repayment->received_by = auth()->id();
            }
        });
    }

    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function reverser(): BelongsTo { return $this->belongsTo(User::class, 'reversed_by'); }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForLoan(Builder $query, int $loanId): Builder
    {
        return $query->where('loan_id', $loanId);
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
            $q->where('repayment_number', 'LIKE', "%{$search}%")
              ->orWhere('reference_number', 'LIKE', "%{$search}%");
        });
    }
}
