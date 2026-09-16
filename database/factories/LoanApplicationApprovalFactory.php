<?php

namespace Database\Factories;

use App\Enums\ApprovalAction;
use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanApplicationApprovalFactory extends Factory
{
    protected $model = LoanApplicationApproval::class;

    public function definition(): array
    {
        return [
            'loan_application_id' => LoanApplication::factory(),
            'approval_level' => 1,
            'action' => ApprovalAction::Pending,
            'level_minimum_amount' => 0,
            'level_maximum_amount' => 1000000,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'action' => ApprovalAction::Approved,
            'acted_by' => 1,
            'acted_at' => now(),
            'comments' => 'Approved.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'action' => ApprovalAction::Rejected,
            'acted_by' => 1,
            'acted_at' => now(),
            'comments' => 'Rejected.',
        ]);
    }
}
