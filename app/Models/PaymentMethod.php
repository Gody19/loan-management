<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'name', 'code', 'type', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (PaymentMethod $m) {
            if (! $m->created_by) {
                $m->created_by = auth()->id();
            }
        });
        static::updating(function (PaymentMethod $m) {
            if (! $m->updated_by) {
                $m->updated_by = auth()->id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
