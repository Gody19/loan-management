<?php

namespace App\Models;

use App\Enums\AiModelVersionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiModelVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'model',
        'display_name',
        'version',
        'status',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiModelVersionStatus::class,
            'configuration' => 'array',
        ];
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AiModelVersionStatus::Active->value);
    }
}