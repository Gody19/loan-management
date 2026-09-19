<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemberDashboardService
{
    private $member;

    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->member = $user->member;
    }

    public function resolve(): array
    {
        $this->member->load(['organization', 'branch', 'vicobaGroup']);

        return [
            'member' => $this->member,
            'savings' => $this->savingsSummary(),
            'shares' => $this->sharesSummary(),
            'welfare' => $this->welfareSummary(),
            'loans' => $this->loanSummary(),
            'recentTransactions' => $this->recentTransactions(),
        ];
    }

    private function savingsSummary(): array
    {
        $accounts = $this->member->savingsAccounts()
            ->with('product')
            ->get();

        return [
            'total_balance' => (float) $accounts->sum('current_balance'),
            'accounts_count' => $accounts->count(),
            'accounts' => $accounts,
        ];
    }

    private function sharesSummary(): array
    {
        $accounts = $this->member->shareAccounts()
            ->with('product')
            ->get();

        return [
            'total_shares' => (int) $accounts->sum('total_shares'),
            'total_value' => (float) $accounts->sum('total_value'),
            'accounts' => $accounts,
        ];
    }

    private function welfareSummary(): array
    {
        $accounts = $this->member->welfareAccounts()
            ->with('fund')
            ->get();

        return [
            'total_balance' => (float) $accounts->sum('current_balance'),
            'accounts_count' => $accounts->count(),
            'accounts' => $accounts,
        ];
    }

    private function loanSummary(): array
    {
        $loans = $this->member->loans()
            ->with('loanPlan')
            ->get();

        $activeLoans = $loans->filter(fn ($loan) => in_array($loan->status->value, ['active', 'disbursed']));

        $nextInstallment = null;
        if ($activeLoans->isNotEmpty()) {
            $loanIds = $activeLoans->pluck('id');
            $nextInstallment = DB::table('loan_repayment_schedules')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'pending')
                ->where('due_date', '>=', now()->toDateString())
                ->orderBy('due_date')
                ->first();
        }

        return [
            'active_count' => $activeLoans->count(),
            'total_outstanding' => (float) $activeLoans->sum('outstanding_balance'),
            'next_installment' => $nextInstallment,
            'all_loans' => $loans,
        ];
    }

    private function recentTransactions(): array
    {
        $memberId = $this->member->id;
        $orgId = $this->member->organization_id;

        $savings = DB::table('savings_transactions')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'transaction_number as reference', 'transaction_type as type', 'amount', 'transaction_date as date', 'status', DB::raw("'savings' as category"))
            ->limit(5)
            ->get();

        $shares = DB::table('share_transactions')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'transaction_number as reference', 'transaction_type as type', 'amount', 'transaction_date as date', 'status', DB::raw("'shares' as category"))
            ->limit(5)
            ->get();

        $welfare = DB::table('welfare_transactions')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'transaction_number as reference', 'transaction_type as type', 'amount', 'transaction_date as date', 'status', DB::raw("'welfare' as category"))
            ->limit(5)
            ->get();

        $repayments = DB::table('loan_repayments')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'repayment_number as reference', DB::raw("'repayment' as type"), 'amount', 'payment_date as date', 'status', DB::raw("'loan_repayment' as category"))
            ->limit(5)
            ->get();

        return $savings->concat($shares)->concat($welfare)->concat($repayments)
            ->sortByDesc('date')
            ->take(10)
            ->values()
            ->toArray();
    }
}
