<?php

namespace App\Models;

use App\Enums\WelfareBenefitRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WelfareBenefitRequest extends Model
{
    use HasFactory;

    protected $table = 'welfare_benefit_requests';

    protected $fillable = [
        'welfare_account_id', 'member_id', 'organization_id', 'branch_id',
        'vicoba_group_id', 'request_number', 'requested_amount', 'reason',
        'status', 'payment_method_id', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'welfare_transaction_id',
        'idempotency_key', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'status' => WelfareBenefitRequestStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (WelfareBenefitRequest $model) {
            if (! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WelfareAccount::class, 'welfare_account_id');
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function welfareTransaction(): BelongsTo
    {
        return $this->belongsTo(WelfareTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
