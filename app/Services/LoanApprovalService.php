<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\LoanApplicationStatus;
use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use App\Models\LoanApprovalLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoanApprovalService
{
    public function __construct(private AuditService $audit) {}

    public function getRequiredLevel(LoanApplication $application): ?LoanApprovalLevel
    {
        return LoanApprovalLevel::where('organization_id', $application->organization_id)
            ->active()
            ->where('minimum_amount', '<=', $application->requested_amount)
            ->where('maximum_amount', '>=', $application->requested_amount)
            ->orderBy('level')
            ->first();
    }

    public function approve(LoanApplication $application, ?string $comments = null): LoanApplicationApproval
    {
        return DB::transaction(function () use ($application, $comments) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);

            if ($application->status !== LoanApplicationStatus::UnderReview) {
                throw new \InvalidArgumentException('Only applications under review can be approved.');
            }

            $level = $this->getRequiredLevel($application);
            $approval = LoanApplicationApproval::create([
                'loan_application_id' => $application->id,
                'loan_approval_level_id' => $level?->id,
                'approval_level' => $level?->level ?? 1,
                'action' => ApprovalAction::Approved,
                'level_minimum_amount' => $level?->minimum_amount,
                'level_maximum_amount' => $level?->maximum_amount,
                'approved_amount' => $application->requested_amount,
                'comments' => $comments,
                'acted_by' => auth()->id(),
                'acted_at' => now(),
            ]);

            $oldStatus = $application->status->value;
            $application->update(['status' => LoanApplicationStatus::Approved]);

            $this->audit->log('loan_application.approved', $application, ['status' => $oldStatus], [
                'status' => 'approved',
                'approved_amount' => $application->requested_amount,
                'approval_level' => $level?->level,
            ]);

            return $approval;
        });
    }

    public function reject(LoanApplication $application, string $reason, ?string $comments = null): LoanApplicationApproval
    {
        if (!$reason) {
            throw new \InvalidArgumentException('Rejection reason is required.');
        }

        return DB::transaction(function () use ($application, $reason, $comments) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);

            if ($application->status !== LoanApplicationStatus::UnderReview) {
                throw new \InvalidArgumentException('Only applications under review can be rejected.');
            }

            $level = $this->getRequiredLevel($application);
            $approval = LoanApplicationApproval::create([
                'loan_application_id' => $application->id,
                'loan_approval_level_id' => $level?->id,
                'approval_level' => $level?->level ?? 1,
                'action' => ApprovalAction::Rejected,
                'level_minimum_amount' => $level?->minimum_amount,
                'level_maximum_amount' => $level?->maximum_amount,
                'comments' => $comments,
                'acted_by' => auth()->id(),
                'acted_at' => now(),
            ]);

            $oldStatus = $application->status->value;
            $application->update([
                'status' => LoanApplicationStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                'rejection_reason' => $reason,
            ]);

            $this->audit->log('loan_application.rejected', $application, ['status' => $oldStatus], [
                'status' => 'rejected',
                'reason' => $reason,
            ]);

            return $approval;
        });
    }
}
