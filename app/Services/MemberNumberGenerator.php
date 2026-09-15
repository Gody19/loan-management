<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

class MemberNumberGenerator
{
    private string $prefix = 'VCB-';
    private int $padding = 6;

    public function generate(): string
    {
        $number = DB::transaction(function () {
            $lastMember = Member::withoutGlobalScopes()
                ->orderByRaw('CAST(SUBSTRING(member_number, ' . (strlen($this->prefix) + 1) . ') AS UNSIGNED) DESC')
                ->first();

            if ($lastMember) {
                $lastNumber = (int) substr($lastMember->member_number, strlen($this->prefix));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $this->prefix . str_pad((string) $nextNumber, $this->padding, '0', STR_PAD_LEFT);
        });

        if (Member::where('member_number', $number)->exists()) {
            return $this->generate();
        }

        return $number;
    }
}
