<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RepaymentNumberGenerator
{
    private string $prefix = 'RPT';

    public function generate(): string
    {
        $year = date('Y');
        $lastRepayment = DB::table('loan_repayments')
            ->where('repayment_number', 'LIKE', "{$this->prefix}-{$year}-%")
            ->orderByDesc('repayment_number')
            ->value('repayment_number');

        if ($lastRepayment) {
            $lastSequence = (int) substr($lastRepayment, -6);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return sprintf('%s-%s-%06d', $this->prefix, $year, $newSequence);
    }
}
