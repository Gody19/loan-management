<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Administrator Credentials
    |--------------------------------------------------------------------------
    |
    | Passwords for the two accounts created by RolePermissionSeeder on a fresh
    | installation. When this is empty the seeder generates a random password
    | and prints it once, which is the safe default: a committed or shared
    | value would otherwise hand a production Super Administrator to anybody
    | who has seen this file.
    |
    */

    'admin_password' => env('FINANCEPRO_ADMIN_PASSWORD'),

    'test_user_password' => env('FINANCEPRO_TEST_USER_PASSWORD'),

];
