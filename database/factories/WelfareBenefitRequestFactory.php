<?php

namespace Database\Factories;

use App\Enums\WelfareBenefitRequestStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareBenefitRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class WelfareBenefitRequestFactory extends Factory
{
    protected $model = WelfareBenefitRequest::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'welfare_account_id' => WelfareAccount::factory(),
            'member_id' => Member::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'request_number' => 'WBR-'.str_pad(self::$counter, 8, '0', STR_PAD_LEFT),
            'requested_amount' => 50000,
            'reason' => fake()->sentence(),
            'status' => WelfareBenefitRequestStatus::Pending,
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => WelfareBenefitRequestStatus::Pending,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => WelfareBenefitRequestStatus::Approved,
            'approved_by' => User::factory(),
            'approved_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => WelfareBenefitRequestStatus::Rejected,
            'rejected_by' => User::factory(),
            'rejected_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
