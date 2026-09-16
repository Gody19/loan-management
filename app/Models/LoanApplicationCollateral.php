<?php

namespace App\Models;

use App\Enums\CollateralType;
use App\Enums\LoanCollateralStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id', 'collateral_type', 'description',
        'estimated_value', 'reference_number', 'ownership_details',
        'notes', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'collateral_type' => CollateralType::class,
            'status' => LoanCollateralStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (LoanApplicationCollateral $c) {
            if (!$c->created_by) $c->created_by = auth()->id();
        });
        static::updating(function (LoanApplicationCollateral $c) {
            if (!$c->updated_by) $c->updated_by = auth()->id();
        });
    }

    public function application(): BelongsTo { return $this->belongsTo(LoanApplication::class, 'loan_application_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
