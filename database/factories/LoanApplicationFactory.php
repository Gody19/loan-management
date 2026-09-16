<?php

namespace Database\Factories;

use App\Enums\LoanApplicationStatus;
use App\Enums\LoanPurpose;
use App\Enums\RepaymentFrequency;
use App\Models\Branch;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\VicobaGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanApplicationFactory extends Factory
{
    protected $model = LoanApplication::class;

    private static int $counter = 0;

    public function definition(): array
    {
        self::$counter++;

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'vicoba_group_id' => VicobaGroup::factory(),
            'member_id' => Member::factory(),
            'loan_plan_id' => LoanPlan::factory(),
            'application_number' => 'LN-' . date('Y') . '-' . str_pad(self::$counter, 6, '0', STR_PAD_LEFT),
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'loan_purpose' => LoanPurpose::Business,
            'purpose_description' => fake()->sentence(),
            'application_date' => now()->toDateString(),
            'status' => LoanApplicationStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => LoanApplicationStatus::Draft]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => LoanApplicationStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => 1,
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status' => LoanApplicationStatus::UnderReview,
            'submitted_at' => now()->subDay(),
            'submitted_by' => 1,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LoanApplicationStatus::Approved,
            'submitted_at' => now()->subDays(2),
            'submitted_by' => 1,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => LoanApplicationStatus::Rejected,
            'submitted_at' => now()->subDays(2),
            'submitted_by' => 1,
            'rejected_at' => now(),
            'rejected_by' => 1,
            'rejection_reason' => 'Does not meet criteria.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => LoanApplicationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => 1,
            'cancellation_reason' => 'Changed my mind.',
        ]);
    }
}
