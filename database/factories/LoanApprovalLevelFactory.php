<?php

namespace Database\Factories;

use App\Models\LoanApprovalLevel;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanApprovalLevelFactory extends Factory
{
    protected $model = LoanApprovalLevel::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Level 1',
            'minimum_amount' => 0,
            'maximum_amount' => 1000000,
            'level' => 1,
            'required_permission' => 'loan_application.approve',
            'is_active' => true,
        ];
    }
}
