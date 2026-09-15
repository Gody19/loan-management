<?php

namespace App\Models;

use App\Enums\FinancialTransactionStatus;
use App\Enums\SavingsTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsTransaction extends Model
{
    use HasFactory;

    protected $table = 'savings_transactions';

    protected $fillable = [
        'savings_account_id',
        'member_id',
        'organization_id',
        'branch_id',
        'vicoba_group_id',
        'transaction_number',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'transaction_date',
        'payment_method_id',
        'reference',
        'description',
        'status',
        'reversed_transaction_id',
        'created_by',
        'approved_by',
        'approved_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
            'transaction_type' => SavingsTransactionType::class,
            'status' => FinancialTransactionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SavingsTransaction $transaction) {
            if (! $transaction->created_by) {
                $transaction->created_by = auth()->id();
            }
        });
    }

    // Relationships

    public function account(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
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

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
