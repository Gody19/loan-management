<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPlanCollateralRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_plan_id', 'minimum_amount', 'maximum_amount',
        'collateral_required', 'coverage_percentage', 'minimum_collateral_value',
        'minimum_assets', 'maximum_assets', 'allowed_collateral_types',
        'required_document_types', 'description', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'collateral_required' => 'boolean',
            'coverage_percentage' => 'decimal:2',
            'minimum_collateral_value' => 'decimal:2',
            'minimum_assets' => 'integer',
            'maximum_assets' => 'integer',
            'allowed_collateral_types' => 'array',
            'required_document_types' => 'array',
            'status' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (LoanPlanCollateralRule $rule) {
            if (!$rule->created_by) $rule->created_by = auth()->id();
        });
        static::updating(function (LoanPlanCollateralRule $rule) {
            if (!$rule->updated_by) $rule->updated_by = auth()->id();
        });
    }

    public function loanPlan(): BelongsTo
    {
        return $this->belongsTo(LoanPlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeForAmount($query, float $amount)
    {
        return $query->where('minimum_amount', '<=', $amount)
            ->where('maximum_amount', '>=', $amount);
    }

    public function scopeForPlan($query, int $planId)
    {
        return $query->where('loan_plan_id', $planId);
    }

    public function allowsCollateralType(string $type): bool
    {
        if (!$this->allowed_collateral_types) {
            return true;
        }
        return in_array($type, $this->allowed_collateral_types);
    }

    public function requiresDocumentType(string $type): bool
    {
        if (!$this->required_document_types) {
            return false;
        }
        return in_array($type, $this->required_document_types);
    }

    public function calculateMinimumCollateralValue(float $loanAmount): float
    {
        $valueFromCoverage = $loanAmount * ($this->coverage_percentage / 100);
        return max($valueFromCoverage, $this->minimum_collateral_value);
    }
}
