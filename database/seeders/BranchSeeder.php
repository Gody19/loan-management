<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        $branchData = [
            1 => [
                ['code' => 'BR-0001', 'name' => 'Dar es Salaam Main', 'phone' => '+255711111111', 'manager' => 'John Mwakasege', 'status' => 'active'],
                ['code' => 'BR-0002', 'name' => 'Kinondoni Branch', 'phone' => '+255712222222', 'manager' => 'Mary Nyerere', 'status' => 'active'],
                ['code' => 'BR-0003', 'name' => 'Temeke Branch', 'phone' => '+255713333333', 'manager' => 'James Mkapa', 'status' => 'active'],
            ],
            2 => [
                ['code' => 'BR-0004', 'name' => 'Arusha Central', 'phone' => '+255721111111', 'manager' => 'Grace Kikwete', 'status' => 'active'],
                ['code' => 'BR-0005', 'name' => 'Arusha Rural', 'phone' => '+255722222222', 'manager' => 'Peter Magufuli', 'status' => 'active'],
            ],
            3 => [
                ['code' => 'BR-0006', 'name' => 'Mwanza Main', 'phone' => '+255731111111', 'manager' => 'Sarah Samia', 'status' => 'active'],
                ['code' => 'BR-0007', 'name' => 'Nyamagana Branch', 'phone' => '+255732222222', 'manager' => 'David Mwinyi', 'status' => 'active'],
            ],
        ];

        foreach ($organizations as $org) {
            $branches = $branchData[$org->id] ?? [];
            foreach ($branches as $branch) {
                $branch['organization_id'] = $org->id;
                Branch::create($branch);
            }
        }

        $this->command->info('Created branches for all organizations.');
    }
}
