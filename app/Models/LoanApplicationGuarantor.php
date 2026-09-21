<?php

namespace App\Models;

use App\Enums\GuarantorStatus;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationGuarantor extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id', 'guarantor_member_id', 'guaranteed_amount',
        'nida_number', 'guarantor_name', 'guarantor_phone', 'guarantor_email',
        'guarantor_relationship', 'guarantor_occupation', 'guarantor_address',
        'status', 'notes', 'confirmed_at', 'confirmed_by',
        'rejected_at', 'rejected_by', 'rejection_reason',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'guaranteed_amount' => 'decimal:2',
            'status' => GuarantorStatus::class,
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (LoanApplicationGuarantor $g) {
            if (!$g->created_by) $g->created_by = auth()->id();
        });
        static::updating(function (LoanApplicationGuarantor $g) {
            if (!$g->updated_by) $g->updated_by = auth()->id();
        });
    }

    public function application(): BelongsTo { return $this->belongsTo(LoanApplication::class, 'loan_application_id'); }
    public function guarantorMember(): BelongsTo { return $this->belongsTo(Member::class, 'guarantor_member_id'); }
    public static function hasActiveGuarantee(int $memberId): bool
    {
        return self::where('guarantor_member_id', $memberId)
            ->where('status', GuarantorStatus::Accepted)
            ->whereHas('application.loan', function ($q) {
                $q->whereNotIn('status', [LoanStatus::Completed, LoanStatus::Cancelled]);
            })
            ->exists();
    }

    public function confirmer(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
    public function rejector(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
