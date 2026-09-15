<?php

namespace App\Models;

use App\Enums\GroupStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VicobaGroup extends Model
{
    use HasFactory;

    protected $table = 'vicoba_groups';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'meeting_day',
        'meeting_time',
        'meeting_location',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => GroupStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', GroupStatus::Active);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
