<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FullTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            PeopleSeeder::class,
            AccountsSeeder::class,
            LoansSeeder::class,
            AccountingSeeder::class,
            AiIntelligenceSeeder::class,
            ManagementActionsSeeder::class,
            ContactMessagesSeeder::class,
            NotificationsSeeder::class,
        ]);

        if ($this->command) {
            $this->command->newLine();
            $this->command->info('Full test data seeding complete.');
        }
    }
}
