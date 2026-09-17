<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_repayment_id', 'loan_id', 'loan_repayment_schedule_id',
        'organization_id', 'amount',
        'principal_allocation', 'interest_allocation', 'fee_allocation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_allocation' => 'decimal:2',
            'interest_allocation' => 'decimal:2',
            'fee_allocation' => 'decimal:2',
        ];
    }

    public function repayment(): BelongsTo { return $this->belongsTo(LoanRepayment::class, 'loan_repayment_id'); }
    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function installment(): BelongsTo { return $this->belongsTo(LoanRepaymentSchedule::class, 'loan_repayment_schedule_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForLoan(Builder $query, int $loanId): Builder
    {
        return $query->where('loan_id', $loanId);
    }
}
