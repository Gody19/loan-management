<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id', 'loan_approval_level_id', 'approval_level',
        'action', 'level_minimum_amount', 'level_maximum_amount',
        'approved_amount', 'comments', 'acted_by', 'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'approval_level' => 'integer',
            'level_minimum_amount' => 'decimal:2',
            'level_maximum_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'action' => ApprovalAction::class,
            'acted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(LoanApplication::class, 'loan_application_id'); }
    public function level(): BelongsTo { return $this->belongsTo(LoanApprovalLevel::class, 'loan_approval_level_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'acted_by'); }
}
