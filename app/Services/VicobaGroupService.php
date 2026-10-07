<?php

namespace App\Services;

use App\Enums\FinancialTransactionStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\SavingsTransactionType;
use App\Enums\WelfareBenefitRequestStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareBenefitRequest;
use Illuminate\Database\Eloquent\Builder;

class VicobaGroupService
{
    public function create(array $data): VicobaGroup
    {
        return VicobaGroup::create($data);
    }

    public function update(VicobaGroup $group, array $data): VicobaGroup
    {
        $group->update($data);

        return $group;
    }

    /**
     * Full detail summary for one VICOBA group: membership, savings, shares,
     * welfare, loan portfolio, applications and income/expenses.
     */
    public function summary(VicobaGroup $group): array
    {
        $memberIds = Member::where('vicoba_group_id', $group->id)->pluck('id');
        $loanIds = Loan::whereIn('member_id', $memberIds)->pluck('id');

        $membersByStatus = Member::where('vicoba_group_id', $group->id)
            ->toBase()
            ->selectRaw('membership_status as status, COUNT(*) as total')
            ->groupBy('membership_status')
            ->pluck('total', 'status');

        $loansByStatus = Loan::whereIn('member_id', $memberIds)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $loanTotals = Loan::whereIn('member_id', $memberIds)
            ->toBase()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(principal_amount), 0) as principal,
                COALESCE(SUM(amount_paid), 0) as repaid,
                COALESCE(SUM(outstanding_balance), 0) as outstanding
            ')
            ->first();

        $applicationsByStatus = LoanApplication::where('vicoba_group_id', $group->id)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $savingsBalance = (float) SavingsAccount::where('vicoba_group_id', $group->id)->sum('current_balance');
        $welfareBalance = (float) WelfareAccount::where('vicoba_group_id', $group->id)->sum('current_balance');
        $shareTotals = ShareAccount::where('vicoba_group_id', $group->id)
            ->toBase()
            ->selectRaw('COALESCE(SUM(total_shares), 0) as shares, COALESCE(SUM(total_value), 0) as value')
            ->first();

        $deposits = (float) SavingsTransaction::where('vicoba_group_id', $group->id)
            ->where('transaction_type', SavingsTransactionType::Deposit)
            ->where('status', FinancialTransactionStatus::Completed)
            ->sum('amount');

        $withdrawals = (float) SavingsTransaction::where('vicoba_group_id', $group->id)
            ->where('transaction_type', SavingsTransactionType::Withdrawal)
            ->where('status', FinancialTransactionStatus::Completed)
            ->sum('amount');

        $interestCollected = (float) LoanRepayment::whereIn('loan_id', $loanIds)
            ->where('status', LoanRepaymentStatus::Posted)
            ->sum('interest_portion');

        $feesCollected = (float) LoanRepayment::whereIn('loan_id', $loanIds)
            ->where('status', LoanRepaymentStatus::Posted)
            ->sum('fee_portion');

        $welfarePaid = (float) WelfareBenefitRequest::where('vicoba_group_id', $group->id)
            ->where('status', WelfareBenefitRequestStatus::Approved)
            ->sum('requested_amount');

        $loansActive = (int) ($loansByStatus[LoanStatus::Disbursed->value] ?? 0)
            + (int) ($loansByStatus[LoanStatus::Active->value] ?? 0);

        $loansPending = (int) ($loansByStatus[LoanStatus::Approved->value] ?? 0)
            + (int) ($loansByStatus[LoanStatus::PendingDisbursement->value] ?? 0);

        $applicationsPending = (int) ($applicationsByStatus[LoanApplicationStatus::Submitted->value] ?? 0)
            + (int) ($applicationsByStatus[LoanApplicationStatus::UnderReview->value] ?? 0);

        $income = $interestCollected + $feesCollected;

        return [
            'members' => [
                'total' => (int) $membersByStatus->sum(),
                'active' => (int) ($membersByStatus[MemberStatus::Active->value] ?? 0),
                'by_status' => $membersByStatus,
            ],
            'financial' => [
                'savings_balance' => $savingsBalance,
                'welfare_balance' => $welfareBalance,
                'shares_count' => (int) ($shareTotals->shares ?? 0),
                'shares_value' => (float) ($shareTotals->value ?? 0),
                'deposits' => $deposits,
                'withdrawals' => $withdrawals,
            ],
            'loans' => [
                'total' => (int) ($loanTotals->total ?? 0),
                'active' => $loansActive,
                'pending' => $loansPending,
                'completed' => (int) ($loansByStatus[LoanStatus::Completed->value] ?? 0),
                'cancelled' => (int) ($loansByStatus[LoanStatus::Cancelled->value] ?? 0),
                'by_status' => $loansByStatus,
                'principal' => (float) ($loanTotals->principal ?? 0),
                'repaid' => (float) ($loanTotals->repaid ?? 0),
                'outstanding' => (float) ($loanTotals->outstanding ?? 0),
            ],
            'applications' => [
                'total' => (int) $applicationsByStatus->sum(),
                'pending' => $applicationsPending,
                'approved' => (int) ($applicationsByStatus[LoanApplicationStatus::Approved->value] ?? 0),
                'rejected' => (int) ($applicationsByStatus[LoanApplicationStatus::Rejected->value] ?? 0),
                'by_status' => $applicationsByStatus,
            ],
            'income' => [
                'interest' => $interestCollected,
                'fees' => $feesCollected,
                'total' => $income,
            ],
            'expenses' => [
                'welfare_paid' => $welfarePaid,
                'total' => $welfarePaid,
            ],
            'net_result' => $income - $welfarePaid,
        ];
    }

    /**
     * Get filtered query scoped to the user's organizations.
     */
    public function getFilteredQuery(array $filters, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        $query = VicobaGroup::with('branch.organization');

        // Tenant scoping: non-Super Admin only sees groups from their org's branches
        if ($user && ! $user->hasRole('Super Administrator')) {
            $orgIds = $user->organizations()->pluck('organizations.id')->toArray();

            if (empty($orgIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('branch', function ($q) use ($orgIds) {
                    $q->whereIn('organization_id', $orgIds);
                });
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('meeting_location', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return $query;
    }

    public function getForUser($user): Builder
    {
        $query = VicobaGroup::with('branch.organization');

        if (! $user->hasRole('Super Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('branch.users', fn ($uq) => $uq->where('users.id', $user->id))
                    ->orWhereHas('branch.organization.users', fn ($uq) => $uq->where('users.id', $user->id));
            });
        }

        return $query;
    }
}
