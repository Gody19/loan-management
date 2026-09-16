<?php

namespace App\Models;

use App\Enums\LoanScheduleInstallmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'organization_id',
        'installment_number', 'due_date',
        'principal_amount', 'interest_amount', 'total_amount',
        'amount_paid', 'outstanding_amount', 'running_balance',
        'status', 'paid_date', 'days_overdue', 'late_fee', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'status' => LoanScheduleInstallmentStatus::class,
            'paid_date' => 'date',
            'days_overdue' => 'integer',
            'late_fee' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForLoan(Builder $query, int $loanId): Builder
    {
        return $query->where('loan_id', $loanId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', LoanScheduleInstallmentStatus::Overdue);
    }
}
