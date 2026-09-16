<?php

namespace Database\Factories;

use App\Enums\CollateralType;
use App\Enums\LoanCollateralStatus;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanApplicationCollateralFactory extends Factory
{
    protected $model = LoanApplicationCollateral::class;

    public function definition(): array
    {
        return [
            'loan_application_id' => LoanApplication::factory(),
            'collateral_type' => CollateralType::Land,
            'description' => fake()->sentence(),
            'estimated_value' => 2000000,
            'reference_number' => fake()->optional()->bothify('REF-####'),
            'ownership_details' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
            'status' => LoanCollateralStatus::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['status' => LoanCollateralStatus::Verified]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => LoanCollateralStatus::Rejected]);
    }
}
