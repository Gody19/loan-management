<?php

namespace Database\Seeders;

use App\Enums\ApprovalAction;
use App\Enums\CollateralDocumentType;
use App\Enums\CollateralType;
use App\Enums\GuarantorStatus;
use App\Enums\InterestMethod;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanCollateralStatus;
use App\Enums\LoanDisbursementStatus;
use App\Enums\LoanPurpose;
use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\Relationship;
use App\Enums\RepaymentFrequency;
use App\Models\CollateralDocument;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanApplicationCollateralSnapshot;
use App\Models\LoanApplicationGuarantor;
use App\Models\LoanApprovalLevel;
use App\Models\LoanDisbursement;
use App\Models\LoanPlan;
use App\Models\LoanPlanCollateralRule;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentAllocation;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\PaymentMethod;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoansSeeder extends Seeder
{
    use FillsTables;

    private array $schedules = [];

    public function run(): void
    {
        $this->applications();
        $this->approvals();
        $this->guarantors();
        $this->collaterals();
        $this->snapshots();
        $this->collateralDocuments();
        $this->loans();
        $this->disbursements();
        $this->schedulesForLoans();
        $this->repayments();

        $this->report('Loan lifecycle ready.');
    }

    private function applications(): void
    {
        $descriptions = [
            LoanPurpose::Business->value => 'Working capital for petty trading inventory',
            LoanPurpose::Agriculture->value => 'Purchase of improved seeds and fertiliser for the season',
            LoanPurpose::Education->value => 'School fees and learning materials for two children',
            LoanPurpose::Emergency->value => 'Urgent medical and family emergency',
            LoanPurpose::Personal->value => 'Household improvement and personal needs',
            LoanPurpose::Development->value => 'Expansion of existing business operations',
            LoanPurpose::Other->value => 'General member development',
        ];

        $statuses = array_merge(
            array_fill(0, 40, LoanApplicationStatus::Approved),
            array_fill(0, 4, LoanApplicationStatus::UnderReview),
            array_fill(0, 3, LoanApplicationStatus::Submitted),
            [LoanApplicationStatus::Draft],
            array_fill(0, 2, LoanApplicationStatus::Rejected),
        );

        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $plans = LoanPlan::where('organization_id', self::ORG_ID)->orderBy('id')->get();

        if (! $members->isEmpty() && ! $plans->isEmpty()) {
            $sequence = LoanApplication::count();

            foreach ($statuses as $index => $status) {
                if ($this->gap('loan_applications') <= 0) {
                    break;
                }

                $member = $members[$sequence % $members->count()];
                $plan = $plans[$index % $plans->count()];
                $purpose = $plan->loan_purpose instanceof LoanPurpose ? $plan->loan_purpose : LoanPurpose::from($this->enumValue($plan->loan_purpose));
                $frequency = $plan->repayment_frequency instanceof RepaymentFrequency ? $plan->repayment_frequency : RepaymentFrequency::from($this->enumValue($plan->repayment_frequency));
                $date = $this->daysAgo(240, 2);
                $sequence++;

                LoanApplication::create([
                    'organization_id' => self::ORG_ID,
                    'branch_id' => $member->branch_id,
                    'vicoba_group_id' => $member->vicoba_group_id,
                    'member_id' => $member->id,
                    'loan_plan_id' => $plan->id,
                    'application_number' => sprintf('LAP-FP-%05d', $sequence),
                    'requested_amount' => $this->money((float) $plan->minimum_amount, (float) $plan->maximum_amount, 10000),
                    'requested_term' => fake()->numberBetween((int) $plan->minimum_term, (int) $plan->maximum_term),
                    'repayment_frequency' => $frequency,
                    'loan_purpose' => $purpose,
                    'purpose_description' => $descriptions[$purpose->value],
                    'application_date' => $date->toDateString(),
                    'status' => $status,
                    'eligibility_snapshot' => [
                        'eligible' => $status !== LoanApplicationStatus::Rejected,
                        'score' => fake()->numberBetween(55, 98),
                        'savings_requirement' => (float) $plan->minimum_savings_balance,
                        'guarantor_requirement' => (int) $plan->minimum_guarantors,
                        'checked_rules' => ['savings_balance', 'guarantors', 'active_loans', 'membership_status'],
                    ],
                    'eligibility_checked_at' => $date->copy()->addHours(6),
                    'submitted_at' => $status === LoanApplicationStatus::Draft ? null : $date->copy()->addHours(8),
                    'submitted_by' => $status === LoanApplicationStatus::Draft ? null : self::ACTOR_ID,
                    'rejected_at' => $status === LoanApplicationStatus::Rejected ? $date->copy()->addDays(3) : null,
                    'rejected_by' => $status === LoanApplicationStatus::Rejected ? self::ACTOR_ID : null,
                    'rejection_reason' => $status === LoanApplicationStatus::Rejected ? 'Member savings balance does not meet the plan requirement.' : null,
                    'created_by' => self::ACTOR_ID,
                ]);

                $this->bump('loan_applications');
            }
        }
    }

    private function approvals(): void
    {
        $applications = LoanApplication::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $levels = LoanApprovalLevel::where('organization_id', self::ORG_ID)->orderBy('level')->get();

        if ($levels->isEmpty()) {
            return;
        }

        foreach ($applications as $application) {
            if ($this->gap('loan_application_approvals') <= 0) {
                break;
            }

            $amount = (float) $application->requested_amount;
            $level = null;

            foreach ($levels as $candidate) {
                if ($amount >= (float) $candidate->minimum_amount && $amount <= (float) $candidate->maximum_amount) {
                    $level = $candidate;
                    break;
                }
            }

            $level ??= $levels->last();
            $status = LoanApplicationStatus::from($this->enumValue($application->status));

            $action = match ($status) {
                LoanApplicationStatus::Approved => ApprovalAction::Approved,
                LoanApplicationStatus::Rejected => ApprovalAction::Rejected,
                default => ApprovalAction::Pending,
            };

            $acted = $action !== ApprovalAction::Pending;

            LoanApplicationApproval::create([
                'loan_application_id' => $application->id,
                'loan_approval_level_id' => $level->id,
                'approval_level' => $level->level,
                'action' => $action,
                'level_minimum_amount' => $level->minimum_amount,
                'level_maximum_amount' => $level->maximum_amount,
                'approved_amount' => $action === ApprovalAction::Approved ? $amount : null,
                'comments' => match ($action) {
                    ApprovalAction::Approved => 'Verified savings and guarantor documentation, approved for disbursement.',
                    ApprovalAction::Rejected => 'Savings balance is below the required multiple for this plan.',
                    default => 'Awaiting committee decision.',
                },
                'acted_by' => $acted ? self::ACTOR_ID : null,
                'acted_at' => $acted ? $application->submitted_at?->copy()->addDay() : null,
            ]);

            $this->bump('loan_application_approvals');
        }
    }

    private function guarantors(): void
    {
        $applications = LoanApplication::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $relationships = [Relationship::Spouse, Relationship::Sibling, Relationship::Parent, Relationship::Guardian, Relationship::Relative, Relationship::Other];
        $statuses = [GuarantorStatus::Accepted, GuarantorStatus::Accepted, GuarantorStatus::Pending, GuarantorStatus::Rejected];

        if ($members->isEmpty()) {
            return;
        }

        foreach ($applications as $index => $application) {
            if ($this->gap('loan_application_guarantors') <= 0) {
                break;
            }

            if (LoanApplicationGuarantor::where('loan_application_id', $application->id)->exists()) {
                continue;
            }

            $guarantor = $members[($index + 3) % $members->count()];

            if ((int) $guarantor->id === (int) $application->member_id) {
                $guarantor = $members[($index + 4) % $members->count()];
            }

            $applicationStatus = LoanApplicationStatus::from($this->enumValue($application->status));
            $internal = $index % 2 === 0;

            // An application still awaiting a decision keeps its guarantor
            // pending, so the guarantor review queue is populated.
            $status = match ($applicationStatus) {
                LoanApplicationStatus::Submitted, LoanApplicationStatus::UnderReview => GuarantorStatus::Pending,
                LoanApplicationStatus::Rejected => GuarantorStatus::Rejected,
                default => $statuses[$index % count($statuses)],
            };

            $accepted = $status === GuarantorStatus::Accepted;
            $rejected = $status === GuarantorStatus::Rejected;
            $date = $this->daysAgo(220, 5);

            LoanApplicationGuarantor::create([
                'loan_application_id' => $application->id,
                'guarantor_member_id' => $internal ? $guarantor->id : null,
                'guaranteed_amount' => round((float) $application->requested_amount / 2, 2),
                'nida_number' => $internal ? $guarantor->national_id : fake()->numerify('199###########'),
                'guarantor_name' => $internal ? trim($guarantor->first_name.' '.(string) $guarantor->last_name) : fake()->name(),
                'guarantor_phone' => $internal ? $guarantor->phone : '+2557'.fake()->numerify('########'),
                'guarantor_email' => $internal ? $guarantor->email : fake()->safeEmail(),
                'guarantor_relationship' => $internal ? $relationships[$index % count($relationships)]->value : 'Business associate',
                'guarantor_occupation' => $internal ? $guarantor->occupation : fake()->jobTitle(),
                'guarantor_address' => $internal ? $guarantor->address : fake()->streetAddress(),
                'status' => $status,
                'notes' => $accepted ? 'Guarantor confirmed the guarantee in writing.' : null,
                'confirmed_at' => $accepted ? $date->copy()->addDay() : null,
                'confirmed_by' => $accepted ? self::ACTOR_ID : null,
                'rejected_at' => $rejected ? $date->copy()->addDays(2) : null,
                'rejected_by' => $rejected ? self::ACTOR_ID : null,
                'rejection_reason' => $rejected ? 'Guarantor declined to guarantee this particular application.' : null,
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('loan_application_guarantors');
        }
    }

    private function collaterals(): void
    {
        $descriptions = [
            CollateralType::Land->value => 'Registered residential plot with title deed',
            CollateralType::Vehicle->value => 'Registered motor vehicle log book',
            CollateralType::Equipment->value => 'Business machinery and equipment',
            CollateralType::Building->value => 'Commercial building under mortgage',
            CollateralType::Jewelry->value => 'Gold ornaments held by the member',
            CollateralType::Savings->value => 'Member savings balance pledged',
            CollateralType::Other->value => 'Other member owned asset',
        ];

        $statuses = [
            LoanCollateralStatus::Verified,
            LoanCollateralStatus::Verified,
            LoanCollateralStatus::UnderReview,
            LoanCollateralStatus::Pending,
            LoanCollateralStatus::Rejected,
        ];

        $applications = LoanApplication::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $types = CollateralType::cases();

        foreach ($applications as $index => $application) {
            if ($this->gap('loan_application_collaterals') <= 0) {
                break;
            }

            $type = $types[$index % count($types)];
            $status = $statuses[$index % count($statuses)];
            $reviewed = in_array($status, [LoanCollateralStatus::Verified, LoanCollateralStatus::Rejected], true);
            $estimated = round((float) $application->requested_amount * $this->pick([1.5, 2.0, 2.5, 3.0]), 2);
            $member = Member::find($application->member_id);

            LoanApplicationCollateral::create([
                'loan_application_id' => $application->id,
                'collateral_type' => $type,
                'description' => $descriptions[$type->value],
                'estimated_value' => $estimated,
                'reviewed_value' => $reviewed ? round($estimated * $this->pick([0.85, 1.0, 1.1]), 2) : null,
                'valuation_date' => $reviewed ? $this->daysAgo(150) : null,
                'valuation_reference' => 'VAL-'.now()->year.'-'.str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'reference_number' => 'COL-'.str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'ownership_details' => 'Registered to member '.($member?->member_number ?? 'unknown'),
                'notes' => null,
                'member_details' => [
                    'member_number' => $member?->member_number,
                    'name' => $member ? trim($member->first_name.' '.$member->last_name) : null,
                    'national_id' => $member?->national_id,
                ],
                'status' => $status,
                'created_by' => self::ACTOR_ID,
                'reviewed_by' => $reviewed ? self::ACTOR_ID : null,
                'reviewed_at' => $reviewed ? $this->daysAgo(140) : null,
                'review_notes' => $status === LoanCollateralStatus::Rejected ? 'Valuation is below the required coverage ratio for this plan.' : null,
            ]);

            $this->bump('loan_application_collaterals');
        }
    }

    private function snapshots(): void
    {
        $applications = LoanApplication::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $rules = LoanPlanCollateralRule::orderBy('id')->get();

        foreach ($applications as $application) {
            if ($this->gap('collateral_snapshots') <= 0) {
                break;
            }

            $amount = (float) $application->requested_amount;
            $rule = null;

            foreach ($rules as $candidate) {
                if ((int) $candidate->loan_plan_id === (int) $application->loan_plan_id
                    && $amount >= (float) $candidate->minimum_amount
                    && $amount <= (float) $candidate->maximum_amount) {
                    $rule = $candidate;
                    break;
                }
            }

            LoanApplicationCollateralSnapshot::create([
                'loan_application_id' => $application->id,
                'loan_plan_id' => $application->loan_plan_id,
                'collateral_rule_id' => $rule?->id,
                'requested_amount' => $amount,
                'collateral_required' => $rule ? (int) (bool) $rule->collateral_required : 0,
                'coverage_percentage' => $rule ? (float) $rule->coverage_percentage : 100,
                'minimum_collateral_value' => $rule ? (float) $rule->minimum_collateral_value : 0,
                'minimum_assets' => $rule ? (int) $rule->minimum_assets : 0,
                'maximum_assets' => $rule ? (int) $rule->maximum_assets : 0,
                'allowed_collateral_types' => $rule?->allowed_collateral_types ?? [],
                'required_document_types' => $rule?->required_document_types ?? [],
                'description' => $rule?->description,
                'snapshot_created_at' => $application->created_at ?? now(),
            ]);

            $this->bump('collateral_snapshots');
        }
    }

    private function collateralDocuments(): void
    {
        $types = CollateralDocumentType::cases();
        $collaterals = LoanApplicationCollateral::orderBy('id')->get();

        foreach ($collaterals as $index => $collateral) {
            if ($this->gap('collateral_documents') <= 0) {
                break;
            }

            $type = $types[$index % count($types)];
            $verified = $index % 3 !== 2;
            $extension = $type === CollateralDocumentType::Photographs ? 'jpg' : 'pdf';

            CollateralDocument::create([
                'loan_application_collateral_id' => $collateral->id,
                'document_type' => $type,
                'file_path' => 'documents/collateral/'.$collateral->id.'/'.fake()->uuid().'.'.$extension,
                'original_filename' => strtolower((string) $collateral->reference_number.'-'.$type->value.'.'.$extension),
                'mime_type' => $extension === 'jpg' ? 'image/jpeg' : 'application/pdf',
                'file_size' => fake()->numberBetween(50000, 3000000),
                'status' => $verified ? 'verified' : 'pending',
                'reviewed_by' => $verified ? self::ACTOR_ID : null,
                'reviewed_at' => $verified ? $this->daysAgo(130) : null,
                'rejection_reason' => null,
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('collateral_documents');
        }
    }

    private function loans(): void
    {
        $applications = LoanApplication::where('organization_id', self::ORG_ID)
            ->where('status', LoanApplicationStatus::Approved->value)
            ->orderBy('id')
            ->get();

        $statuses = array_merge(
            array_fill(0, 22, LoanStatus::Active),
            array_fill(0, 8, LoanStatus::Completed),
            array_fill(0, 4, LoanStatus::Disbursed),
            array_fill(0, 3, LoanStatus::PendingDisbursement),
            array_fill(0, 3, LoanStatus::Cancelled),
        );

        $plans = LoanPlan::where('organization_id', self::ORG_ID)->get()->keyBy('id');
        $sequence = Loan::count();

        foreach ($applications as $index => $application) {
            if ($this->gap('loans', 40) <= 0) {
                break;
            }

            $status = $statuses[$index % count($statuses)];
            $plan = $plans[$application->loan_plan_id] ?? null;
            $principal = round((float) $application->requested_amount, 2);
            $rate = (float) ($plan?->interest_rate ?? 12);
            $term = (int) $application->requested_term;
            $interest = round($principal * ($rate / 100) * ($term / 12), 2);
            $frequency = $application->repayment_frequency instanceof RepaymentFrequency
                ? $application->repayment_frequency
                : RepaymentFrequency::from($this->enumValue($application->repayment_frequency));
            $paymentsPerYear = ['weekly' => 52, 'biweekly' => 26, 'monthly' => 12, 'quarterly' => 4][$frequency->value] ?? 12;
            $disbursed = in_array($status, [LoanStatus::Active, LoanStatus::Completed, LoanStatus::Disbursed], true);
            $disbursementDate = $disbursed ? $this->daysAgo(220, 10) : null;
            $maturity = $disbursementDate?->copy()->addMonths($term);
            $sequence++;

            Loan::create([
                'organization_id' => self::ORG_ID,
                'branch_id' => $application->branch_id,
                'member_id' => $application->member_id,
                'loan_plan_id' => $application->loan_plan_id,
                'loan_application_id' => $application->id,
                'disbursed_by' => $disbursed ? self::ACTOR_ID : null,
                'loan_number' => sprintf('LN-FP-%05d', $sequence),
                'principal_amount' => $principal,
                'disbursed_amount' => $disbursed ? $principal : 0,
                'interest_rate' => $rate,
                'interest_method' => $plan?->interest_method ?? InterestMethod::Flat,
                'term_months' => $term,
                'repayment_frequency' => $frequency,
                'total_interest' => $interest,
                'total_amount' => round($principal + $interest, 2),
                'processing_fee' => (float) ($plan?->processing_fee ?? 0),
                'insurance_fee' => (float) ($plan?->insurance_fee ?? 0),
                'amount_paid' => 0,
                'outstanding_balance' => round($principal + $interest, 2),
                'grace_period' => (int) ($plan?->grace_period ?? 0),
                'status' => $status,
                'disbursement_date' => $disbursementDate?->toDateString(),
                'maturity_date' => $maturity?->toDateString(),
                'next_payment_date' => $disbursementDate?->copy()->addMonths(max(1, (int) round(12 / $paymentsPerYear)))->toDateString(),
                'installments_paid' => 0,
                'total_installments' => max(1, (int) round($term / 12 * $paymentsPerYear)),
                'notes' => 'Standard loan schedule generated from the plan terms.',
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('loans');
        }
    }

    private function disbursements(): void
    {
        $loans = Loan::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $methods = PaymentMethod::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $sequence = DB::table('loan_disbursements')->count();

        foreach ($loans as $loan) {
            if ($this->gap('loan_disbursements', 40) <= 0) {
                break;
            }

            $loanStatus = LoanStatus::from($this->enumValue($loan->status));

            $status = match ($loanStatus) {
                LoanStatus::Cancelled => LoanDisbursementStatus::Rejected,
                LoanStatus::PendingDisbursement, LoanStatus::Approved => LoanDisbursementStatus::Pending,
                default => LoanDisbursementStatus::Confirmed,
            };

            $amount = (float) $loan->principal_amount;
            $fees = (float) $loan->processing_fee + (float) $loan->insurance_fee;
            $date = $loan->disbursement_date ? Carbon::parse($loan->disbursement_date) : $this->daysAgo(30, 3);
            $sequence++;

            LoanDisbursement::create([
                'loan_id' => $loan->id,
                'organization_id' => self::ORG_ID,
                'branch_id' => $loan->branch_id,
                'payment_method_id' => $this->pick($methods),
                'processed_by' => self::ACTOR_ID,
                'disbursement_number' => sprintf('DIS-%s-%05d', $date->year, $sequence),
                'amount' => $amount,
                'processing_fee' => $loan->processing_fee,
                'insurance_fee' => $loan->insurance_fee,
                'net_amount' => round(max(0, $amount - $fees), 2),
                'disbursement_date' => $date->toDateString(),
                'status' => $status,
                'disbursement_method' => $this->pick(['cash', 'bank', 'mobile_money']),
                'reference_number' => 'DISREF'.fake()->numerify('########'),
                'notes' => $status === LoanDisbursementStatus::Confirmed ? 'Funds released to the member in person.' : null,
                'rejection_reason' => $status === LoanDisbursementStatus::Rejected ? 'Loan cancelled before disbursement.' : null,
            ]);

            $this->bump('loan_disbursements');
        }
    }

    private function schedulesForLoans(): void
    {
        $loans = Loan::where('organization_id', self::ORG_ID)
            ->whereIn('status', [LoanStatus::Active->value, LoanStatus::Disbursed->value, LoanStatus::Completed->value])
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            if ($this->gap('loan_repayment_schedules', 150) <= 0) {
                break;
            }

            $status = LoanStatus::from($this->enumValue($loan->status));
            $installments = min((int) $loan->total_installments, 12);
            $principal = (float) $loan->principal_amount;
            $perPrincipal = $principal / $installments;
            $perInterest = $principal * ((float) $loan->interest_rate / 100) / 12;
            $start = $loan->disbursement_date ? Carbon::parse($loan->disbursement_date)->addMonth() : now()->addMonth();

            $paidTarget = match ($status) {
                LoanStatus::Completed => $installments,
                LoanStatus::Disbursed => fake()->numberBetween(0, 1),
                default => fake()->numberBetween(0, max(0, $installments - 3)),
            };

            $running = $principal;
            $paidAmount = 0.0;
            $paidCount = 0;

            for ($index = 1; $index <= $installments; $index++) {
                $dueDate = $start->copy()->addMonths($index - 1);
                $principalPortion = $index === $installments ? round($running, 2) : round($perPrincipal, 2);
                $interestPortion = round($perInterest, 2);
                $totalPortion = round($principalPortion + $interestPortion, 2);
                $paid = $index <= $paidTarget;
                $overdue = ! $paid && $dueDate->isPast();
                $amountPaid = $paid ? $totalPortion : 0.0;
                $running = max(0, $running - $principalPortion);

                $schedule = LoanRepaymentSchedule::create([
                    'loan_id' => $loan->id,
                    'organization_id' => self::ORG_ID,
                    'installment_number' => $index,
                    'due_date' => $dueDate->toDateString(),
                    'principal_amount' => $principalPortion,
                    'interest_amount' => $interestPortion,
                    'total_amount' => $totalPortion,
                    'amount_paid' => $amountPaid,
                    'outstanding_amount' => round($totalPortion - $amountPaid, 2),
                    'running_balance' => round($running, 2),
                    'status' => $paid
                        ? LoanScheduleInstallmentStatus::Paid
                        : ($overdue ? LoanScheduleInstallmentStatus::Overdue : LoanScheduleInstallmentStatus::Pending),
                    'paid_date' => $paid ? $dueDate->copy()->subDays(fake()->numberBetween(0, 4))->toDateString() : null,
                    'days_overdue' => $overdue ? (int) $dueDate->diffInDays(now()) : 0,
                    'late_fee' => $overdue ? round($totalPortion * 0.02, 2) : 0,
                    'notes' => null,
                ]);

                $this->schedules[] = $schedule;
                $paidAmount += $amountPaid;
                $paidCount += $paid ? 1 : 0;
                $this->bump('loan_repayment_schedules');
            }

            $loan->update([
                'installments_paid' => $status === LoanStatus::Completed ? (int) $loan->total_installments : $paidCount,
                'amount_paid' => $status === LoanStatus::Completed ? (float) $loan->total_amount : round($paidAmount, 2),
                'outstanding_balance' => $status === LoanStatus::Completed ? 0 : round(max(0, (float) $loan->total_amount - $paidAmount), 2),
            ]);
        }
    }

    private function repayments(): void
    {
        $paid = array_values(array_filter(
            $this->schedules,
            fn ($schedule) => $schedule->status === LoanScheduleInstallmentStatus::Paid
        ));

        if (! $paid) {
            return;
        }

        $methods = PaymentMethod::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $sequence = DB::table('loan_repayments')->count();

        foreach ($paid as $index => $schedule) {
            if ($this->gap('loan_repayments') <= 0) {
                break;
            }

            $loan = Loan::find($schedule->loan_id);

            if (! $loan) {
                continue;
            }

            $amount = (float) $schedule->amount_paid;
            $reversed = $index > 0 && $index % 13 === 0;
            $paymentDate = $schedule->paid_date ?? $schedule->due_date;
            $sequence++;

            $repayment = LoanRepayment::create([
                'loan_id' => $loan->id,
                'organization_id' => self::ORG_ID,
                'branch_id' => $loan->branch_id,
                'member_id' => $loan->member_id,
                'payment_method_id' => $this->pick($methods),
                'received_by' => self::ACTOR_ID,
                'repayment_number' => sprintf('REP-%s-%05d', Carbon::parse($paymentDate)->year, $sequence),
                'amount' => $amount,
                'principal_portion' => (float) $schedule->principal_amount,
                'interest_portion' => (float) $schedule->interest_amount,
                'fee_portion' => (float) $schedule->late_fee,
                'overpayment_amount' => 0,
                'payment_date' => Carbon::parse($paymentDate)->toDateString(),
                'payment_method' => $this->pick(['cash', 'bank', 'mobile_money']),
                'reference_number' => 'RCP-'.str_pad($sequence, 6, '0', STR_PAD_LEFT),
                'status' => $reversed ? LoanRepaymentStatus::Reversed : LoanRepaymentStatus::Posted,
                'reversal_reason' => $reversed ? 'Duplicate receipt captured during cash count reconciliation.' : null,
                'idempotency_key' => 'rep-'.str_pad($sequence, 6, '0', STR_PAD_LEFT),
                'notes' => $reversed ? null : 'Instalment payment received at the branch counter.',
                'reversed_by' => $reversed ? self::ACTOR_ID : null,
                'reversal_date' => $reversed ? now()->subDays(2)->toDateString() : null,
            ]);

            if ($reversed) {
                $schedule->update([
                    'status' => $schedule->due_date->isPast() ? LoanScheduleInstallmentStatus::Overdue : LoanScheduleInstallmentStatus::Pending,
                    'amount_paid' => 0,
                    'outstanding_amount' => (float) $schedule->total_amount,
                    'paid_date' => null,
                ]);

                $loan->update([
                    'installments_paid' => max(0, (int) $loan->installments_paid - 1),
                    'amount_paid' => round(max(0, (float) $loan->amount_paid - $amount), 2),
                    'outstanding_balance' => round((float) $loan->outstanding_balance + $amount, 2),
                ]);
            } else {
                LoanRepaymentAllocation::create([
                    'loan_repayment_id' => $repayment->id,
                    'loan_id' => $loan->id,
                    'loan_repayment_schedule_id' => $schedule->id,
                    'organization_id' => self::ORG_ID,
                    'amount' => $amount,
                    'principal_allocation' => (float) $schedule->principal_amount,
                    'interest_allocation' => (float) $schedule->interest_amount,
                    'fee_allocation' => (float) $schedule->late_fee,
                    'status' => 'active',
                ]);

                $this->bump('loan_repayment_allocations');
            }

            $this->bump('loan_repayments');
        }
    }
}
