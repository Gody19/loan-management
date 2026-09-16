<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DisbursementNumberGenerator
{
    private const PREFIX = 'DIS';

    public function generate(): string
    {
        return DB::transaction(function () {
            $year = date('Y');
            $prefix = self::PREFIX . '-' . $year . '-';

            $last = DB::table('loan_disbursements')
                ->where('disbursement_number', 'LIKE', $prefix . '%')
                ->orderBy('disbursement_number', 'desc')
                ->lockForUpdate()
                ->first();

            if ($last) {
                $lastNumber = (int) substr($last->disbursement_number, -6);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }
}
