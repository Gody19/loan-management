<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            ['name' => 'Tanzania VICOBA Federation', 'registration_number' => 'ORG-000001', 'phone' => '+255712345678', 'email' => 'info@tvf.co.tz', 'region' => 'Dar es Salaam', 'district' => 'Ilala', 'status' => 'active'],
            ['name' => 'Arusha Community Finance', 'registration_number' => 'ORG-000002', 'phone' => '+255723456789', 'email' => 'info@acf.co.tz', 'region' => 'Arusha', 'district' => 'Arusha', 'status' => 'active'],
            ['name' => 'Mwanza Youth Finance', 'registration_number' => 'ORG-000003', 'phone' => '+255734567890', 'email' => 'info@myf.co.tz', 'region' => 'Mwanza', 'district' => 'Nyamagana', 'status' => 'active'],
        ];

        foreach ($organizations as $org) {
            Organization::create($org);
        }

        $this->command->info('Created '.count($organizations).' organizations.');
    }
}
