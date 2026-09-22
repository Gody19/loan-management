<?php

namespace App\Services;

use App\Enums\GuarantorStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanCollateralStatus;
use App\Models\CollateralDocument;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;
use App\Models\LoanPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

            // Validate guarantor requirements - always require at least 1
            $guarantorQuery = LoanApplicationGuarantor::where('loan_application_id', $application->id)
                ->where('status', '!=', GuarantorStatus::Rejected);

            $guarantorCount = $guarantorQuery->count();

            if ($guarantorCount < 1) {
                throw new \InvalidArgumentException(
                    'Application requires at least 1 guarantor. Currently has 0. Please add a guarantor before submitting.'
                );
            }

            if ($guarantorCount > 2) {
                throw new \InvalidArgumentException(
                    'Application allows a maximum of 2 guarantors. Currently has ' . $guarantorCount . '.'
                );
            }

            $eligibilityService = app(\App\Services\GuarantorEligibilityService::class);
            $activeGuarantors = $guarantorQuery->where('status', '!=', GuarantorStatus::Rejected)->get();
            foreach ($activeGuarantors as $g) {
                if ($g->guarantor_member_id) {
                    $elig = $eligibilityService->canGuarantee($g->guarantorMember, $application);
                    if (!$elig['eligible']) {
                        throw new \InvalidArgumentException(
                            "Guarantor (Member #{$g->guarantorMember->member_number}) is not eligible: {$elig['reason']}"
                        );
                    }
                }
            }

            // Validate collateral requirements using the collateral rule engine
            $collateralService = app(\App\Services\CollateralRequirementService::class);
            $collateralErrors = $collateralService->validateCollateral($application);
            if (!empty($collateralErrors)) {
                throw new \InvalidArgumentException('Collateral requirement not satisfied: ' . implode(' ', $collateralErrors));
            }

            // Create collateral requirement snapshot
            $collateralService->createSnapshot($application);

            $application->update([
                'status' => LoanApplicationStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => auth()->id(),
                'eligibility_snapshot' => [
                    'eligible' => $eligibilityResult->eligible,
                    'requested_amount' => $eligibilityResult->requestedAmount,
                    'approved_amount' => $eligibilityResult->approvedAmount,
                    'active_loan_count' => $eligibilityResult->activeLoanCount,
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
        if (!in_array($guarantor->status, [GuarantorStatus::Pending, GuarantorStatus::Rejected])) {
            throw new \InvalidArgumentException('Only pending or rejected guarantors can be removed.');
        }

        $application = $guarantor->application;
        $guarantor->delete();

        $this->audit->log('loan_application.guarantor_removed', $application, [
            'guarantor_member_id' => $guarantor->guarantor_member_id,
        ], []);
    }

    public function updateGuarantor(LoanApplicationGuarantor $guarantor, array $data): LoanApplicationGuarantor
    {
        if (!in_array($guarantor->status, [GuarantorStatus::Pending, GuarantorStatus::Rejected])) {
            throw new \InvalidArgumentException('Only pending or rejected guarantors can be edited.');
        }

        $old = $guarantor->toArray();
        $guarantor->update([
            'guaranteed_amount' => $data['guaranteed_amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->log('loan_application.guarantor_updated', $guarantor->application, $old, $guarantor->fresh()->toArray());

        return $guarantor;
    }

    public function respondToGuarantor(LoanApplicationGuarantor $guarantor, bool $accept, ?string $reason = null, ?string $nidaNumber = null, bool $requireNida = false, array $details = []): LoanApplicationGuarantor
    {
        if ($guarantor->status !== GuarantorStatus::Pending) {
            throw new \InvalidArgumentException('This guarantor has already been reviewed.');
        }

        if ($accept) {
            $eligibilityService = app(\App\Services\GuarantorEligibilityService::class);
            if ($guarantor->guarantor_member_id) {
                $elig = $eligibilityService->canGuarantee($guarantor->guarantorMember, $guarantor->application, $guarantor->id);
                if (!$elig['eligible']) {
                    throw new \InvalidArgumentException('Guarantor is not eligible: ' . $elig['reason']);
                }
            }

            $updateData = [
                'status' => GuarantorStatus::Accepted,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ];

            if (!empty($details['guaranteed_amount'])) {
                $updateData['guaranteed_amount'] = (float) $details['guaranteed_amount'];
            }

            $guarantor->update($updateData);
            $this->audit->log('loan_application.guarantor_approved', $guarantor->application, [], [
                'guarantor_member_id' => $guarantor->guarantor_member_id,
                'reviewed_by' => auth()->id(),
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
                'reviewed_by' => auth()->id(),
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

    public function verifyCollateral(LoanApplicationCollateral $collateral, array $data = []): LoanApplicationCollateral
    {
        $old = $collateral->toArray();
        $collateral->update([
            'status' => LoanCollateralStatus::Verified,
            'reviewed_value' => $data['reviewed_value'] ?? $collateral->estimated_value,
            'valuation_date' => $data['valuation_date'] ?? null,
            'valuation_reference' => $data['valuation_reference'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        $this->audit->log('loan_application.collateral_approved', $collateral->application, $old, [
            'status' => 'verified',
            'reviewed_value' => $collateral->reviewed_value,
            'reviewed_by' => auth()->id(),
        ]);

        return $collateral;
    }

    public function rejectCollateral(LoanApplicationCollateral $collateral, string $reason): LoanApplicationCollateral
    {
        $old = $collateral->toArray();
        $collateral->update([
            'status' => LoanCollateralStatus::Rejected,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);

        $this->audit->log('loan_application.collateral_rejected', $collateral->application, $old, [
            'status' => 'rejected',
            'reason' => $reason,
            'reviewed_by' => auth()->id(),
        ]);

        return $collateral;
    }

    public function addCollateralDocument(LoanApplicationCollateral $collateral, array $data, $file): CollateralDocument
    {
        $safeFilename = $collateral->id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('collateral-documents/' . $collateral->id, $safeFilename, 'private');

        $document = CollateralDocument::create([
            'loan_application_collateral_id' => $collateral->id,
            'document_type' => $data['document_type'],
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        $this->audit->log('loan_application.collateral_document_uploaded', $collateral->application, [], [
            'collateral_id' => $collateral->id,
            'document_type' => $data['document_type'],
            'filename' => $file->getClientOriginalName(),
        ]);

        return $document;
    }

    public function removeCollateralDocument(CollateralDocument $document): void
    {
        Storage::disk('private')->delete($document->file_path);
        $document->delete();
    }
}
