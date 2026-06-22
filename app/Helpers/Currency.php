<?php

namespace App\Helpers;

class Currency
{
    public static function tzs(float|int $amount): string
    {
        return 'TZS '.number_format($amount, 0, '.', ',');
    }
}
