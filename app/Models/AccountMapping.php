<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    protected $fillable = [
        'organization_id', 'mapping_key', 'chart_of_account_id',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id'); }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForKey($query, string $key)
    {
        return $query->where('mapping_key', $key);
    }
}
