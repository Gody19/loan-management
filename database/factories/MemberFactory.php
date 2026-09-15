<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\VicobaGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $phone = '+255'.fake()->numerify('7########');

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'member_number' => 'VCB-'.str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'first_name' => $firstName,
            'middle_name' => fake()->optional(0.3)->firstName(),
            'last_name' => $lastName,
            'gender' => fake()->randomElement(Gender::values()),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'phone' => $phone,
            'alternate_phone' => fake()->optional(0.3)->numerify('+2557#########'),
            'email' => fake()->optional(0.5)->safeEmail(),
            'national_id' => fake()->optional(0.8)->numerify('##################'),
            'occupation' => fake()->randomElement([
                'Farmer', 'Teacher', 'Trader', 'Driver', 'Nurse',
                'Mechanic', 'Tailor', 'Business Owner', 'Student', 'Laborer',
            ]),
            'employer_or_business' => fake()->optional(0.4)->company(),
            'marital_status' => fake()->randomElement(MaritalStatus::values()),
            'address' => fake()->optional(0.5)->address(),
            'region' => fake()->randomElement([
                'Dar es Salaam', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya',
                'Tanga', 'Morogoro', 'Kilimanjaro', 'Tabora', 'Iringa',
            ]),
            'district' => fake()->city(),
            'ward' => fake()->optional(0.6)->citySuffix(),
            'street' => fake()->optional(0.4)->streetName(),
            'joining_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'membership_status' => fake()->randomElement(MemberStatus::values()),
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['membership_status' => MemberStatus::Pending]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['membership_status' => MemberStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['membership_status' => MemberStatus::Suspended]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['membership_status' => MemberStatus::Inactive]);
    }
}
