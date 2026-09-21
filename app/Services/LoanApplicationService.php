<?php

namespace App\Services;

use App\Enums\GuarantorStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanCollateralStatus;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;
use App\Models\LoanPlan;
use Illuminate\Support\Facades\DB;

class LoanApplicationService
{
    public function __construct(
        private ApplicationNumberGenerator $numberGenerator,
        private LoanEligibilityService $eligibilityService,
        private AuditService $audit,
    ) {}

    public function create(array $data, Member $member, LoanPlan $plan): LoanApplication
    {
        $applicationNumber = $this->numberGenerator->generate();

        $application = LoanApplication::create([
            'organization_id' => $member->organization_id,
            'branch_id' => $data['branch_id'],
            'vicoba_group_id' => $data['vicoba_group_id'] ?? $member->vicoba_group_id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'application_number' => $applicationNumber,
            'requested_amount' => $data['requested_amount'],
            'requested_term' => $data['requested_term'],
            'repayment_frequency' => $data['repayment_frequency'],
            'loan_purpose' => $data['loan_purpose'],
            'purpose_description' => $data['purpose_description'] ?? null,
            'application_date' => now()->toDateString(),
            'status' => LoanApplicationStatus::Draft,
        ]);

        $this->audit->log('loan_application.created', $application, [], $application->toArray());

        return $application;
    }

    public function submit(LoanApplication $application): LoanApplication
    {
        return DB::transaction(function () use ($application) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);

            if (!$application->status->canTransitionTo(LoanApplicationStatus::Submitted)) {
                throw new \InvalidArgumentException('Application cannot be submitted in its current status.');
            }

            // Re-run eligibility
            $member = $application->member;
            $plan = $application->loanPlan;
            $eligibilityResult = $this->eligibilityService->checkEligibility(
                $member,
                $plan,
                (float) $application->requested_amount,
                $application->requested_term
            );

            if (!$eligibilityResult->eligible) {
                throw new \InvalidArgumentException('Member is not eligible: ' . implode('; ', $eligibilityResult->failureReasons));
            }

            // Validate guarantor requirements
            if ($plan->requires_guarantor) {
                $guarantorCount = LoanApplicationGuarantor::where('loan_application_id', $application->id)
                    ->where('status', GuarantorStatus::Accepted)
                    ->count();
                if ($guarantorCount < $plan->minimum_guarantors) {
                    throw new \InvalidArgumentException(
                        "Application requires at least {$plan->minimum_guarantors} accepted guarantor(s). Currently has {$guarantorCount}."
                    );
                }
            }

            // Validate collateral requirements
            if ($plan->requires_collateral) {
                $collateralCount = LoanApplicationCollateral::where('loan_application_id', $application->id)
                    ->where('status', LoanCollateralStatus::Verified)
                    ->count();
                if ($collateralCount === 0) {
                    throw new \InvalidArgumentException('Application requires at least one verified collateral.');
                }
            }

            $application->update([
                'status' => LoanApplicationStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => auth()->id(),
                'eligibility_snapshot' => [
                    'eligible' => $eligibilityResult->eligible,
                    'requested_amount' => $eligibilityResult->requestedAmount,
                    'approved_amount' => $eligibilityResult->approvedAmount,
                    'total_savings' => $eligibilityResult->totalSavings,
                    'total_shares' => $eligibilityResult->totalShares,
                    'active_loan_count' => $eligibilityResult->activeLoanCount,
                    'max_allowed_by_savings' => $eligibilityResult->maxAllowedBySavings,
                    'max_allowed_by_shares' => $eligibilityResult->maxAllowedByShares,
                    'checks' => $eligibilityResult->checks,
                    'failure_reasons' => $eligibilityResult->failureReasons,
                    'calculated_at' => now()->toISOString(),
                ],
                'eligibility_checked_at' => now(),
            ]);

            $this->audit->log('loan_application.submitted', $application, ['status' => 'draft'], ['status' => 'submitted']);

            return $application;
        });
    }

    public function cancel(LoanApplication $application, ?string $reason = null): LoanApplication
    {
        return DB::transaction(function () use ($application, $reason) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);

            if (!$application->status->canTransitionTo(LoanApplicationStatus::Cancelled)) {
                throw new \InvalidArgumentException('Application cannot be cancelled in its current status.');
            }

            $oldStatus = $application->status->value;
            $application->update([
                'status' => LoanApplicationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $reason,
            ]);

            $this->audit->log('loan_application.cancelled', $application, ['status' => $oldStatus], ['status' => 'cancelled']);

            return $application;
        });
    }

    public function addToReview(LoanApplication $application): LoanApplication
    {
        return DB::transaction(function () use ($application) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);

            if (!$application->status->canTransitionTo(LoanApplicationStatus::UnderReview)) {
                throw new \InvalidArgumentException('Application cannot be moved to review in its current status.');
            }

            $oldStatus = $application->status->value;
            $application->update(['status' => LoanApplicationStatus::UnderReview]);

            $this->audit->log('loan_application.review', $application, ['status' => $oldStatus], ['status' => 'under_review']);

            return $application;
        });
    }

    public function addGuarantor(LoanApplication $application, array $data, ?Member $guarantorMember): LoanApplicationGuarantor
    {
        if ($guarantorMember) {
            // Rule 1: No self-guaranteeing
            if ($guarantorMember->id === $application->member_id) {
                throw new \InvalidArgumentException('A member cannot guarantee their own application.');
            }

            // Rule 3: Same organization
            if ($guarantorMember->organization_id !== $application->organization_id) {
                throw new \InvalidArgumentException('Guarantor must belong to the same organization.');
            }

            // Rule 4: Active member
            if ($guarantorMember->membership_status->value !== 'active') {
                throw new \InvalidArgumentException('Guarantor must be an active member.');
            }

            // Rule 2: No duplicate
            $exists = LoanApplicationGuarantor::where('loan_application_id', $application->id)
                ->where('guarantor_member_id', $guarantorMember->id)
                ->exists();
            if ($exists) {
                throw new \InvalidArgumentException('This member is already a guarantor for this application.');
            }
        }

        $guarantor = LoanApplicationGuarantor::create([
            'loan_application_id' => $application->id,
            'guarantor_member_id' => $guarantorMember?->id,
            'guaranteed_amount' => $data['guaranteed_amount'],
            'nida_number' => $data['nida_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => GuarantorStatus::Pending,
            'guarantor_name' => $data['guarantor_name'] ?? null,
            'guarantor_phone' => $data['guarantor_phone'] ?? null,
            'guarantor_email' => $data['guarantor_email'] ?? null,
            'guarantor_relationship' => $data['guarantor_relationship'] ?? null,
            'guarantor_occupation' => $data['guarantor_occupation'] ?? null,
            'guarantor_address' => $data['guarantor_address'] ?? null,
        ]);

        $this->audit->log('loan_application.guarantor_added', $application, [], [
            'guarantor_member_id' => $guarantorMember?->id,
            'guarantor_name' => $data['guarantor_name'] ?? null,
            'guaranteed_amount' => $data['guaranteed_amount'],
        ]);

        return $guarantor;
    }

    public function removeGuarantor(LoanApplicationGuarantor $guarantor): void
    {
        if ($guarantor->status !== GuarantorStatus::Pending) {
            throw new \InvalidArgumentException('Only pending guarantors can be removed.');
        }

        $application = $guarantor->application;
        $guarantor->delete();

        $this->audit->log('loan_application.guarantor_removed', $application, [
            'guarantor_member_id' => $guarantor->guarantor_member_id,
        ], []);
    }

    public function respondToGuarantor(LoanApplicationGuarantor $guarantor, bool $accept, ?string $reason = null, ?string $nidaNumber = null, bool $requireNida = false): LoanApplicationGuarantor
    {
        if ($guarantor->status !== GuarantorStatus::Pending) {
            throw new \InvalidArgumentException('Guarantor has already responded.');
        }

        if ($accept) {
            if ($requireNida) {
                if (!$nidaNumber) {
                    throw new \InvalidArgumentException('NIDA number is required to accept a guarantor request.');
                }

                $nidaTrimmed = trim($nidaNumber);
                if (strlen($nidaTrimmed) < 6) {
                    throw new \InvalidArgumentException('NIDA number must be at least 6 characters.');
                }

                $nidaExists = LoanApplicationGuarantor::where('nida_number', $nidaTrimmed)
                    ->where('id', '!=', $guarantor->id)
                    ->where('status', '!=', GuarantorStatus::Rejected)
                    ->exists();
                if ($nidaExists) {
                    throw new \InvalidArgumentException('This NIDA number is already registered by another guarantor.');
                }

                if ($guarantor->guarantor_member_id && LoanApplicationGuarantor::hasActiveGuarantee($guarantor->guarantor_member_id)) {
                    throw new \InvalidArgumentException('You cannot guarantee another loan because you still have an active guaranteed loan that has not been fully repaid.');
                }
            } else {
                $nidaTrimmed = $nidaNumber ? trim($nidaNumber) : null;
            }

            $guarantor->update([
                'nida_number' => $nidaTrimmed,
                'status' => GuarantorStatus::Accepted,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);
            $this->audit->log('loan_application.guarantor_accepted', $guarantor->application, [], [
                'guarantor_member_id' => $guarantor->guarantor_member_id,
                'nida_number' => $nidaTrimmed,
            ]);
        } else {
            if (!$reason) {
                throw new \InvalidArgumentException('Rejection reason is required.');
            }
            $guarantor->update([
                'status' => GuarantorStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                'rejection_reason' => $reason,
            ]);
            $this->audit->log('loan_application.guarantor_rejected', $guarantor->application, [], [
                'guarantor_member_id' => $guarantor->guarantor_member_id,
                'reason' => $reason,
            ]);
        }

        return $guarantor;
    }

    public function addCollateral(LoanApplication $application, array $data): LoanApplicationCollateral
    {
        $collateral = LoanApplicationCollateral::create([
            'loan_application_id' => $application->id,
            'collateral_type' => $data['collateral_type'],
            'description' => $data['description'],
            'estimated_value' => $data['estimated_value'],
            'reference_number' => $data['reference_number'] ?? null,
            'ownership_details' => $data['ownership_details'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => LoanCollateralStatus::Pending,
        ]);

        $this->audit->log('loan_application.collateral_added', $application, [], [
            'collateral_type' => $data['collateral_type'],
            'estimated_value' => $data['estimated_value'],
        ]);

        return $collateral;
    }

    public function removeCollateral(LoanApplicationCollateral $collateral): void
    {
        $application = $collateral->application;
        $collateral->delete();
        $this->audit->log('loan_application.collateral_removed', $application, [
            'collateral_type' => $collateral->collateral_type->value,
        ], []);
    }
}
