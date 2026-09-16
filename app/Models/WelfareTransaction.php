<?php

namespace App\Models;

use App\Enums\FinancialTransactionStatus;
use App\Enums\WelfareTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WelfareTransaction extends Model
{
    use HasFactory;

    protected $table = 'welfare_transactions';

    protected $fillable = [
        'welfare_account_id', 'member_id', 'organization_id', 'branch_id',
        'vicoba_group_id', 'transaction_number', 'transaction_type', 'amount',
        'balance_before', 'balance_after', 'transaction_date', 'payment_method_id',
        'reference', 'description', 'status', 'reversed_transaction_id',
        'reversed_by', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
            'transaction_type' => WelfareTransactionType::class,
            'status' => FinancialTransactionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (WelfareTransaction $t) {
            if (! $t->created_by) {
                $t->created_by = auth()->id();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(WelfareTransaction::class, 'reversed_transaction_id');
    }
}
