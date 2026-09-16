<?php

namespace App\Models;

use App\Enums\FinancialTransactionStatus;
use App\Enums\ShareTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareTransaction extends Model
{
    use HasFactory;

    protected $table = 'share_transactions';

    protected $fillable = [
        'share_account_id',
        'member_id',
        'organization_id',
        'branch_id',
        'vicoba_group_id',
        'transaction_number',
        'transaction_type',
        'quantity',
        'share_price',
        'amount',
        'balance_shares_before',
        'balance_shares_after',
        'balance_value_before',
        'balance_value_after',
        'transaction_date',
        'payment_method_id',
        'reference',
        'description',
        'status',
        'reversed_transaction_id',
        'reversed_by',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'share_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'balance_value_before' => 'decimal:2',
            'balance_value_after' => 'decimal:2',
            'transaction_date' => 'date',
            'transaction_type' => ShareTransactionType::class,
            'status' => FinancialTransactionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (ShareTransaction $t) {
            if (! $t->created_by) {
                $t->created_by = auth()->id();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ShareAccount::class, 'share_account_id');
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
        return $this->belongsTo(ShareTransaction::class, 'reversed_transaction_id');
    }
}
