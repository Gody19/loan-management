<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionNumberGenerator
{
    private int $padding = 8;

    private array $tableMap = [
        'SVT' => 'savings_transactions',
        'SHT' => 'share_transactions',
        'WFT' => 'welfare_transactions',
        'WBR' => 'welfare_benefit_requests',
        'RCT' => 'receipts',
    ];

    private array $numberColumnMap = [
        'WBR' => 'request_number',
    ];

    public function generate(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $table = $this->tableMap[$prefix] ?? null;

            $column = $this->numberColumnMap[$prefix] ?? 'transaction_number';

            if ($table && Schema::hasTable($table)) {
                $last = DB::table($table)
                    ->lockForUpdate()
                    ->orderByRaw("CAST(SUBSTRING({$column}, ".(strlen($prefix) + 1).') AS UNSIGNED) DESC')
                    ->value($column);
            } else {
                $last = DB::table('savings_transactions')
                    ->lockForUpdate()
                    ->where('transaction_number', 'LIKE', $prefix.'%')
                    ->orderByRaw('CAST(SUBSTRING(transaction_number, '.(strlen($prefix) + 1).') AS UNSIGNED) DESC')
                    ->value('transaction_number');
            }

            if ($last) {
                $lastNumber = (int) substr($last, strlen($prefix));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix.str_pad((string) $nextNumber, $this->padding, '0', STR_PAD_LEFT);
        });
    }
}
