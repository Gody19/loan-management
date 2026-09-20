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
            'upcomingPayments' => $this->upcomingPayments(),
            'recentTransactions' => $this->recentTransactions(),
            'pendingApplications' => $this->pendingApplications(),
            'pendingGuarantorRequests' => $this->pendingGuarantorRequests(),
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
        $completedLoans = $loans->filter(fn ($loan) => $loan->status->value === 'completed');

        $totalPaid = (float) $activeLoans->sum('amount_paid');
        $totalOutstanding = (float) $activeLoans->sum('outstanding_balance');

        $overdueAmount = 0;
        $overdueInstallments = 0;
        $nextInstallment = null;
        $upcomingPayments = collect();

        if ($activeLoans->isNotEmpty()) {
            $loanIds = $activeLoans->pluck('id');

            $overdueAmount = (float) DB::table('loan_repayment_schedules')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'overdue')
                ->sum('outstanding_amount');

            $overdueInstallments = (int) DB::table('loan_repayment_schedules')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'overdue')
                ->count();

            $nextInstallment = DB::table('loan_repayment_schedules')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'pending')
                ->where('due_date', '>=', now()->toDateString())
                ->orderBy('due_date')
                ->first();

            $upcomingPayments = DB::table('loan_repayment_schedules')
                ->whereIn('loan_id', $loanIds)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->where('due_date', '>=', now()->toDateString())
                ->orderBy('due_date')
                ->limit(5)
                ->get();
        }

        return [
            'active_count' => $activeLoans->count(),
            'completed_count' => $completedLoans->count(),
            'total_outstanding' => $totalOutstanding,
            'total_paid' => $totalPaid,
            'overdue_amount' => $overdueAmount,
            'overdue_installments' => $overdueInstallments,
            'next_installment' => $nextInstallment,
            'upcoming_payments' => $upcomingPayments,
            'all_loans' => $loans,
        ];
    }

    private function upcomingPayments(): \Illuminate\Support\Collection
    {
        $activeLoans = $this->member->loans()
            ->whereIn('status', ['active', 'disbursed'])
            ->pluck('id');

        if ($activeLoans->isEmpty()) {
            return collect();
        }

        return DB::table('loan_repayment_schedules')
            ->whereIn('loan_id', $activeLoans)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->limit(5)
            ->get();
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

    private function pendingApplications(): int
    {
        return $this->member->loanApplications()
            ->whereIn('status', ['draft', 'submitted', 'under_review'])
            ->count();
    }

    private function pendingGuarantorRequests(): int
    {
        return \App\Models\LoanApplicationGuarantor::where('guarantor_member_id', $this->member->id)
            ->where('status', \App\Enums\GuarantorStatus::Pending)
            ->count();
    }
}
