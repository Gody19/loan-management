<?php

namespace Database\Factories;

use App\Enums\WelfareAccountStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use Illuminate\Database\Eloquent\Factories\Factory;

class WelfareAccountFactory extends Factory
{
    protected $model = WelfareAccount::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'member_id' => Member::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'welfare_fund_id' => WelfareFund::factory(),
            'account_number' => 'WFA-'.str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'current_balance' => 0,
            'status' => WelfareAccountStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => WelfareAccountStatus::Inactive,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => WelfareAccountStatus::Closed,
        ]);
    }
}
