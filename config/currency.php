<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency code used throughout the application.
    | This should match the APP_CURRENCY value in your .env file.
    |
    */

    'code' => env('APP_CURRENCY', 'TZS'),

    /*
    |--------------------------------------------------------------------------
    | Currency Symbol
    |--------------------------------------------------------------------------
    |
    | The symbol used to display currency values (e.g., TSh, $, €).
    |
    */

    'symbol' => env('APP_CURRENCY_SYMBOL', 'TSh'),

    /*
    |--------------------------------------------------------------------------
    | Currency Name
    |--------------------------------------------------------------------------
    |
    | The full name of the default currency.
    |
    */

    'name' => 'Tanzanian Shilling',

    /*
    |--------------------------------------------------------------------------
    | Decimal Places
    |--------------------------------------------------------------------------
    |
    | The number of decimal places for currency values.
    |
    */

    'decimals' => 2,

    /*
    |--------------------------------------------------------------------------
    | Thousand Separator
    |--------------------------------------------------------------------------
    |
    | The character used to separate thousands (e.g., 1,000,000).
    |
    */

    'thousands_separator' => ',',

    /*
    |--------------------------------------------------------------------------
    | Decimal Separator
    |--------------------------------------------------------------------------
    |
    | The character used to separate decimal places (e.g., 10.50).
    |
    */

    'decimal_separator' => '.',

];
