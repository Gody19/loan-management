<?php

namespace App\Services;

use App\Models\SavingsAccount;
use Illuminate\Support\Facades\DB;

class SavingsAccountNumberGenerator
{
    private string $prefix = 'SAV-';

    private int $padding = 6;

    public function generate(): string
    {
        return DB::transaction(function () {
            $last = SavingsAccount::withoutGlobalScopes()
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(account_number, '.(strlen($this->prefix) + 1).') AS UNSIGNED) DESC')
                ->first();

            if ($last) {
                $lastNumber = (int) substr($last->account_number, strlen($this->prefix));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            $number = $this->prefix.str_pad((string) $nextNumber, $this->padding, '0', STR_PAD_LEFT);

            // Protect against concurrent duplicates
            $exists = SavingsAccount::withoutGlobalScopes()
                ->where('account_number', $number)
                ->exists();

            if ($exists) {
                return $this->generate();
            }

            return $number;
        });
    }
}
