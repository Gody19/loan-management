<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\VicobaGroup;
use Illuminate\Database\Seeder;

class VicobaGroupSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        $groupNames = [
            'Jeshi', 'Umoja', 'Maendeleo', 'Ujamaa', 'Amani',
            'Tumaini', 'Furaha', 'Baraka', 'Imani', 'Upendo',
        ];

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $times = ['09:00', '10:00', '14:00', '15:00'];

        foreach ($branches as $branch) {
            $numGroups = rand(2, 4);
            for ($i = 0; $i < $numGroups; $i++) {
                VicobaGroup::create([
                    'branch_id' => $branch->id,
                    'code' => 'GRP-'.str_pad($branch->id * 10 + $i, 4, '0', STR_PAD_LEFT),
                    'name' => $groupNames[array_rand($groupNames)].' '.($i + 1),
                    'meeting_day' => $days[array_rand($days)],
                    'meeting_time' => $times[array_rand($times)],
                    'meeting_location' => $branch->name.' Hall',
                    'description' => 'VICOBA group meeting at '.$branch->name,
                    'status' => 'active',
                ]);
            }
        }

        $this->command->info('Created VICOBA groups for all branches.');
    }
}
