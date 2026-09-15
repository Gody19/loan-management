<?php

namespace Database\Factories;

use App\Enums\ShareAccountStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShareAccountFactory extends Factory
{
    protected $model = ShareAccount::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        $totalShares = 100;
        $sharePrice = 10000;

        return [
            'member_id' => Member::factory(),
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'share_product_id' => ShareProduct::factory(),
            'account_number' => 'SHR-'.str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'total_shares' => $totalShares,
            'total_value' => $totalShares * $sharePrice,
            'status' => ShareAccountStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => ShareAccountStatus::Inactive,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => ShareAccountStatus::Closed,
        ]);
    }
}
