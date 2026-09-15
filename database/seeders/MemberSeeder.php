<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberNextOfKin;
use App\Models\Organization;
use App\Models\VicobaGroup;
use App\Services\MemberNumberGenerator;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $generator = new MemberNumberGenerator;
        $organizations = Organization::all();
        $branches = Branch::all();
        $groups = VicobaGroup::all();

        if ($organizations->isEmpty() || $branches->isEmpty() || $groups->isEmpty()) {
            $this->command->warn('Please run Organization, Branch, and Group seeders first.');

            return;
        }

        $firstNames = [
            'John', 'Mary', 'James', 'Grace', 'Peter', 'Sarah', 'David', 'Ruth',
            'Joseph', 'Hannah', 'Samuel', 'Esther', 'Daniel', 'Naomi', 'Emmanuel',
            'Rebecca', 'Michael', 'Martha', 'Robert', 'Lydia', 'William', 'Deborah',
            'Charles', 'Rachel', 'Daniel', 'Leah', 'Andrew', 'Elizabeth', 'Thomas',
            'Joyce', 'Stephen', 'Agnes', 'Patrick', 'Catherine', 'Benjamin', 'Rose',
            'George', 'Alice', 'Henry', 'Victoria', 'Edward', 'Julia', 'Frank',
            'Margaret', 'Richard', 'Sandra', 'Joseph', 'Diana', 'Edward', 'Nancy',
        ];

        $lastNames = [
            'Mwakasege', 'Nyerere', 'Mkapa', 'Kikwete', 'Magufuli', 'Samia',
            'Mwinyi', 'Mandela', 'Oginga', 'Kenyatta', 'Moi', 'Kibaki',
            'Mwega', 'Njonjo', 'Matiba', 'Saitoti', 'Tanganyika', 'Uhuru',
            'Khalifa', 'Abdulla', 'Hassan', 'Ibrahim', 'Omar', 'Ali',
            'Mohamed', 'Salum', 'Hamad', 'Juma', 'Hemed', 'Issa',
        ];

        $occupations = [
            'Farmer', 'Teacher', 'Trader', 'Driver', 'Nurse',
            'Mechanic', 'Tailor', 'Business Owner', 'Student', 'Laborer',
            'Carpenter', 'Fisherman', 'Miner', 'Cook', 'Mason',
        ];

        $regions = [
            'Dar es Salaam', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya',
            'Tanga', 'Morogoro', 'Kilimanjaro', 'Tabora', 'Iringa',
        ];

        $statuses = MemberStatus::values();
        $genders = Gender::values();

        $members = [];

        for ($i = 0; $i < 60; $i++) {
            $org = $organizations->random();
            $branch = $branches->where('organization_id', $org->id)->random();
            $group = $groups->where('branch_id', $branch->id)->random();

            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $members[] = Member::create([
                'organization_id' => $org->id,
                'branch_id' => $branch->id,
                'vicoba_group_id' => $group->id,
                'member_number' => $generator->generate(),
                'first_name' => $firstName,
                'middle_name' => fake()->optional(0.3)->firstName(),
                'last_name' => $lastName,
                'gender' => $genders[array_rand($genders)],
                'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
                'phone' => '+255'.fake()->numerify('7########'),
                'alternate_phone' => fake()->optional(0.3)->numerify('+2557#########'),
                'email' => fake()->optional(0.5)->safeEmail(),
                'national_id' => fake()->numerify('##################'),
                'occupation' => $occupations[array_rand($occupations)],
                'employer_or_business' => fake()->optional(0.4)->company(),
                'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
                'region' => $regions[array_rand($regions)],
                'district' => fake()->city(),
                'ward' => fake()->optional(0.6)->citySuffix(),
                'joining_date' => fake()->dateTimeBetween('-3 years', 'now'),
                'membership_status' => $statuses[array_rand($statuses)],
            ]);
        }

        foreach ($members as $member) {
            MemberNextOfKin::create([
                'member_id' => $member->id,
                'full_name' => fake()->name(),
                'relationship' => fake()->randomElement(['spouse', 'parent', 'child', 'sibling']),
                'phone' => '+255'.fake()->numerify('7########'),
                'address' => fake()->optional(0.5)->address(),
                'is_primary' => true,
            ]);

            if (fake()->boolean(30)) {
                MemberNextOfKin::create([
                    'member_id' => $member->id,
                    'full_name' => fake()->name(),
                    'relationship' => fake()->randomElement(['parent', 'sibling', 'relative']),
                    'phone' => '+255'.fake()->numerify('7########'),
                    'address' => fake()->optional(0.5)->address(),
                    'is_primary' => false,
                ]);
            }
        }

        $this->command->info('Created '.count($members).' members with next-of-kin records.');
    }
}
