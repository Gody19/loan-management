<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationCollateralSnapshot extends Model
{
    use HasFactory;

    protected $table = 'collateral_snapshots';

    protected $fillable = [
        'loan_application_id', 'loan_plan_id', 'collateral_rule_id',
        'requested_amount', 'collateral_required', 'coverage_percentage',
        'minimum_collateral_value', 'minimum_assets', 'maximum_assets',
        'allowed_collateral_types', 'required_document_types',
        'description', 'snapshot_created_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'collateral_required' => 'boolean',
            'coverage_percentage' => 'decimal:2',
            'minimum_collateral_value' => 'decimal:2',
            'minimum_assets' => 'integer',
            'maximum_assets' => 'integer',
            'allowed_collateral_types' => 'array',
            'required_document_types' => 'array',
            'snapshot_created_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function loanPlan(): BelongsTo
    {
        return $this->belongsTo(LoanPlan::class);
    }

    public function collateralRule(): BelongsTo
    {
        return $this->belongsTo(LoanPlanCollateralRule::class, 'collateral_rule_id');
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
}
