<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LoanNumberGenerator
{
    private const PREFIX = 'LN';

    public function generate(): string
    {
        return DB::transaction(function () {
            $year = date('Y');
            $prefix = self::PREFIX . '-' . $year . '-';

            $lastLoan = DB::table('loans')
                ->where('loan_number', 'LIKE', $prefix . '%')
                ->orderBy('loan_number', 'desc')
                ->lockForUpdate()
                ->first();

            if ($lastLoan) {
                $lastNumber = (int) substr($lastLoan->loan_number, -6);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }
}
