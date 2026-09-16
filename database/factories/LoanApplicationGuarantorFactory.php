<?php

namespace Database\Factories;

use App\Enums\GuarantorStatus;
use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanApplicationGuarantorFactory extends Factory
{
    protected $model = LoanApplicationGuarantor::class;

    public function definition(): array
    {
        return [
            'loan_application_id' => LoanApplication::factory(),
            'guarantor_member_id' => Member::factory(),
            'guaranteed_amount' => 250000,
            'status' => GuarantorStatus::Pending,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => GuarantorStatus::Accepted,
            'confirmed_at' => now(),
            'confirmed_by' => 1,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => GuarantorStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => 1,
            'rejection_reason' => 'Cannot guarantee.',
        ]);
    }
}
