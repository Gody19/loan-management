<?php

namespace App\Models;

use App\Enums\LoanDisbursementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'organization_id', 'branch_id', 'payment_method_id',
        'processed_by',
        'disbursement_number', 'amount', 'processing_fee', 'insurance_fee',
        'net_amount', 'disbursement_date', 'status', 'disbursement_method',
        'reference_number', 'notes', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'disbursement_date' => 'date',
            'status' => LoanDisbursementStatus::class,
        ];
    }

    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
