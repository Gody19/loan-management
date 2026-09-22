<?php

namespace App\Models;

use App\Enums\CollateralDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollateralDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_collateral_id', 'document_type', 'file_path',
        'original_filename', 'mime_type', 'file_size',
        'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => CollateralDocumentType::class,
            'file_size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (CollateralDocument $doc) {
            if (!$doc->created_by) $doc->created_by = auth()->id();
        });
    }

    public function collateral(): BelongsTo
    {
        return $this->belongsTo(LoanApplicationCollateral::class, 'loan_application_collateral_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForCollateral($query, int $collateralId)
    {
        return $query->where('loan_application_collateral_id', $collateralId);
    }
}
