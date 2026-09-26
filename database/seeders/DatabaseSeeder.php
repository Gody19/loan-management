<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OrganizationSeeder::class,
            BranchSeeder::class,
            VicobaGroupSeeder::class,
            MemberSeeder::class,
            FinancialSeeder::class,
            AiPublicKnowledgeSeeder::class,
        ]);
    }
}
