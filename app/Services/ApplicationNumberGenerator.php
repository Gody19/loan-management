<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApplicationNumberGenerator
{
    private const PREFIX = 'LN';

    public function generate(): string
    {
        return DB::transaction(function () {
            $year = date('Y');
            $prefix = self::PREFIX . '-' . $year . '-';

            $lastApplication = DB::table('loan_applications')
                ->where('application_number', 'LIKE', $prefix . '%')
                ->orderBy('application_number', 'desc')
                ->lockForUpdate()
                ->first();

            if ($lastApplication) {
                $lastNumber = (int) substr($lastApplication->application_number, -6);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }
}
