<?php

namespace App\Models;

use App\Enums\Relationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberNextOfKin extends Model
{
    protected $table = 'member_next_of_kins';

    protected $fillable = [
        'member_id',
        'full_name',
        'relationship',
        'phone',
        'alternate_phone',
        'address',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'relationship' => Relationship::class,
            'is_primary' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
