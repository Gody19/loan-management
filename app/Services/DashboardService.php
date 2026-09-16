<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareTransaction;

class DashboardService
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function resolve(): array
    {
        if ($this->user->hasRole('Super Administrator')) {
            return $this->superAdminDashboard();
        }

        if ($this->user->hasRole('Organization Administrator')) {
            return $this->orgAdminDashboard();
        }

        if ($this->user->hasRole('Branch Manager')) {
            return $this->branchManagerDashboard();
        }

        if ($this->user->hasRole('VICOBA Member')) {
            return $this->memberDashboard();
        }

        return $this->staffDashboard();
    }

    private function superAdminDashboard(): array
    {
        $orgIds = Organization::pluck('id');

        return [
            'dashboard_type' => 'super_admin',
            'title' => 'Platform Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => 'Platform Administration',
            'widgets' => [
                'organizations' => [
                    'total' => Organization::count(),
                    'active' => Organization::where('is_active', true)->count(),
                ],
                'branches' => ['total' => Branch::count()],
                'groups' => ['total' => VicobaGroup::count(), 'active' => VicobaGroup::where('status', 'active')->count()],
                'members' => [
                    'total' => Member::count(),
                    'active' => Member::where('membership_status', 'active')->count(),
                    'pending' => Member::where('membership_status', 'pending')->count(),
                ],
                'users' => ['total' => User::count(), 'active' => User::where('status', 'active')->count()],
                'savings' => [
                    'total_balance' => SavingsAccount::where('status', 'active')->sum('current_balance'),
                    'accounts' => SavingsAccount::where('status', 'active')->count(),
                ],
                'shares' => [
                    'total_shares' => ShareAccount::where('status', 'active')->sum('total_shares'),
                    'total_value' => ShareAccount::where('status', 'active')->sum('total_value'),
                ],
                'welfare' => [
                    'total_balance' => WelfareAccount::where('status', 'active')->sum('current_balance'),
                ],
                'loans' => [
                    'plans' => LoanPlan::count(),
                    'applications' => LoanApplication::count(),
                    'pending' => LoanApplication::where('status', 'submitted')->count(),
                    'under_review' => LoanApplication::where('status', 'under_review')->count(),
                    'approved' => LoanApplication::where('status', 'approved')->count(),
                    'rejected' => LoanApplication::where('status', 'rejected')->count(),
                ],
            ],
            'recent_activity' => $this->getRecentActivity(),
        ];
    }

    private function orgAdminDashboard(): array
    {
        $orgIds = $this->user->organizations()->pluck('organizations.id');

        $memberQuery = Member::whereIn('organization_id', $orgIds);
        $savingsQuery = SavingsAccount::whereIn('organization_id', $orgIds);
        $shareQuery = ShareAccount::whereIn('organization_id', $orgIds);
        $welfareQuery = WelfareAccount::whereIn('organization_id', $orgIds);
        $loanQuery = LoanApplication::whereIn('organization_id', $orgIds);

        return [
            'dashboard_type' => 'org_admin',
            'title' => 'Organization Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => $this->user->organizations()->pluck('organizations.name')->first() ?? 'Organization',
            'widgets' => [
                'organization' => [
                    'branches' => Branch::whereIn('organization_id', $orgIds)->count(),
                    'groups' => VicobaGroup::whereIn('branch_id', Branch::whereIn('organization_id', $orgIds)->pluck('id'))->count(),
                ],
                'members' => [
                    'total' => $memberQuery->count(),
                    'active' => $memberQuery->where('membership_status', 'active')->count(),
                    'pending' => $memberQuery->where('membership_status', 'pending')->count(),
                    'suspended' => $memberQuery->where('membership_status', 'suspended')->count(),
                ],
                'savings' => [
                    'accounts' => $savingsQuery->where('status', 'active')->count(),
                    'total_balance' => $savingsQuery->where('status', 'active')->sum('current_balance'),
                ],
                'shares' => [
                    'accounts' => $shareQuery->where('status', 'active')->count(),
                    'total_shares' => $shareQuery->where('status', 'active')->sum('total_shares'),
                    'total_value' => $shareQuery->where('status', 'active')->sum('total_value'),
                ],
                'welfare' => [
                    'accounts' => $welfareQuery->where('status', 'active')->count(),
                    'total_balance' => $welfareQuery->where('status', 'active')->sum('current_balance'),
                ],
                'loans' => [
                    'plans' => LoanPlan::whereIn('organization_id', $orgIds)->count(),
                    'applications' => $loanQuery->count(),
                    'pending' => $loanQuery->where('status', 'submitted')->count(),
                    'under_review' => $loanQuery->where('status', 'under_review')->count(),
                    'approved' => $loanQuery->where('status', 'approved')->count(),
                    'rejected' => $loanQuery->where('status', 'rejected')->count(),
                ],
            ],
            'recent_activity' => $this->getRecentActivity($orgIds),
        ];
    }

    private function branchManagerDashboard(): array
    {
        $branchIds = $this->user->branches()->pluck('branches.id');

        $memberQuery = Member::whereIn('branch_id', $branchIds);
        $savingsQuery = SavingsAccount::whereIn('branch_id', $branchIds);
        $shareQuery = ShareAccount::whereIn('branch_id', $branchIds);
        $welfareQuery = WelfareAccount::whereIn('branch_id', $branchIds);
        $loanQuery = LoanApplication::whereIn('branch_id', $branchIds);

        return [
            'dashboard_type' => 'branch_manager',
            'title' => 'Branch Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => 'Branch: ' . ($this->user->branches()->pluck('branches.name')->first() ?? 'Unassigned'),
            'widgets' => [
                'branch' => [
                    'groups' => VicobaGroup::whereIn('branch_id', $branchIds)->count(),
                ],
                'members' => [
                    'total' => $memberQuery->count(),
                    'active' => $memberQuery->where('membership_status', 'active')->count(),
                    'pending' => $memberQuery->where('membership_status', 'pending')->count(),
                ],
                'savings' => [
                    'accounts' => $savingsQuery->where('status', 'active')->count(),
                    'total_balance' => $savingsQuery->where('status', 'active')->sum('current_balance'),
                ],
                'shares' => [
                    'total_shares' => $shareQuery->where('status', 'active')->sum('total_shares'),
                    'total_value' => $shareQuery->where('status', 'active')->sum('total_value'),
                ],
                'welfare' => [
                    'total_balance' => $welfareQuery->where('status', 'active')->sum('current_balance'),
                ],
                'loans' => [
                    'applications' => $loanQuery->count(),
                    'pending' => $loanQuery->where('status', 'submitted')->count(),
                    'under_review' => $loanQuery->where('status', 'under_review')->count(),
                    'approved' => $loanQuery->where('status', 'approved')->count(),
                ],
            ],
            'recent_activity' => $this->getRecentActivity(null, $branchIds),
        ];
    }

    private function staffDashboard(): array
    {
        $orgIds = $this->user->organizations()->pluck('organizations.id');
        $branchIds = $this->user->branches()->pluck('branches.id');
        $hasOrg = $orgIds->isNotEmpty();
        $hasBranch = $branchIds->isNotEmpty();

        $widgets = [];

        if ($this->user->can('member.view')) {
            $q = Member::query();
            if ($hasBranch) $q->whereIn('branch_id', $branchIds);
            elseif ($hasOrg) $q->whereIn('organization_id', $orgIds);

            $widgets['members'] = [
                'total' => $q->count(),
                'active' => (clone $q)->where('membership_status', 'active')->count(),
            ];
        }

        if ($this->user->can('savings_account.view')) {
            $q = SavingsAccount::query();
            if ($hasBranch) $q->whereIn('branch_id', $branchIds);
            elseif ($hasOrg) $q->whereIn('organization_id', $orgIds);

            $widgets['savings'] = [
                'accounts' => $q->where('status', 'active')->count(),
                'total_balance' => (clone $q)->where('status', 'active')->sum('current_balance'),
            ];
        }

        if ($this->user->can('share_account.view')) {
            $q = ShareAccount::query();
            if ($hasBranch) $q->whereIn('branch_id', $branchIds);
            elseif ($hasOrg) $q->whereIn('organization_id', $orgIds);

            $widgets['shares'] = [
                'total_shares' => $q->where('status', 'active')->sum('total_shares'),
                'total_value' => $q->where('status', 'active')->sum('total_value'),
            ];
        }

        if ($this->user->can('welfare_account.view')) {
            $q = WelfareAccount::query();
            if ($hasBranch) $q->whereIn('branch_id', $branchIds);
            elseif ($hasOrg) $q->whereIn('organization_id', $orgIds);

            $widgets['welfare'] = [
                'total_balance' => $q->where('status', 'active')->sum('current_balance'),
            ];
        }

        if ($this->user->can('loan_application.view')) {
            $q = LoanApplication::query();
            if ($hasBranch) $q->whereIn('branch_id', $branchIds);
            elseif ($hasOrg) $q->whereIn('organization_id', $orgIds);

            $widgets['loans'] = [
                'applications' => $q->count(),
                'pending' => (clone $q)->where('status', 'submitted')->count(),
                'under_review' => (clone $q)->where('status', 'under_review')->count(),
                'approved' => (clone $q)->where('status', 'approved')->count(),
            ];
        }

        return [
            'dashboard_type' => 'staff',
            'title' => 'Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => $this->getStaffScopeLabel(),
            'widgets' => $widgets,
            'recent_activity' => $this->getRecentActivity($hasOrg ? $orgIds : null, $hasBranch ? $branchIds : null),
        ];
    }

    private function memberDashboard(): array
    {
        $member = $this->user->member;

        if (!$member) {
            return [
                'dashboard_type' => 'member',
                'title' => 'My Dashboard',
                'welcome' => 'Welcome back, ' . $this->user->fullname,
                'scope_label' => 'Member',
                'widgets' => [],
                'recent_activity' => collect(),
            ];
        }

        $widgets = [];

        if ($this->user->can('savings.view')) {
            $widgets['savings'] = [
                'balance' => SavingsAccount::where('member_id', $member->id)->where('status', 'active')->sum('current_balance'),
                'accounts' => SavingsAccount::where('member_id', $member->id)->where('status', 'active')->count(),
            ];
        }

        if ($this->user->can('shares.view')) {
            $widgets['shares'] = [
                'total_shares' => ShareAccount::where('member_id', $member->id)->where('status', 'active')->sum('total_shares'),
                'total_value' => ShareAccount::where('member_id', $member->id)->where('status', 'active')->sum('total_value'),
            ];
        }

        if ($this->user->can('loan_application.view')) {
            $widgets['loans'] = [
                'applications' => LoanApplication::where('member_id', $member->id)->count(),
                'pending' => LoanApplication::where('member_id', $member->id)->where('status', 'submitted')->count(),
                'approved' => LoanApplication::where('member_id', $member->id)->where('status', 'approved')->count(),
            ];
        }

        $recentActivity = collect();
        if ($this->user->can('savings.view')) {
            $recentActivity = $recentActivity->concat(
                SavingsTransaction::whereHas('account', fn ($q) => $q->where('member_id', $member->id))
                    ->where('status', 'completed')->latest()->take(5)->get()
                    ->map(fn ($t) => ['type' => 'savings', 'label' => 'Savings ' . $t->transaction_type, 'amount' => $t->amount, 'date' => $t->created_at])
            );
        }

        return [
            'dashboard_type' => 'member',
            'title' => 'My Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => 'Member: ' . $member->full_name,
            'widgets' => $widgets,
            'recent_activity' => $recentActivity->sortByDesc('date')->take(10)->values(),
        ];
    }

    private function getRecentActivity($orgIds = null, $branchIds = null): \Illuminate\Support\Collection
    {
        $orgArray = $orgIds instanceof \Illuminate\Support\Collection ? $orgIds->toArray() : (is_array($orgIds) ? $orgIds : null);
        $branchArray = $branchIds instanceof \Illuminate\Support\Collection ? $branchIds->toArray() : (is_array($branchIds) ? $branchIds : null);

        $recentSavings = SavingsTransaction::with('account.member')
            ->where('status', 'completed');
        $recentShares = ShareTransaction::with('account.member')
            ->where('status', 'completed');
        $recentWelfare = WelfareTransaction::with('account.member')
            ->where('status', 'completed');

        if ($branchArray) {
            $recentSavings->whereHas('account', fn ($q) => $q->whereIn('branch_id', $branchArray));
            $recentShares->whereHas('account', fn ($q) => $q->whereIn('branch_id', $branchArray));
            $recentWelfare->whereHas('account', fn ($q) => $q->whereIn('branch_id', $branchArray));
        } elseif ($orgArray) {
            $recentSavings->whereHas('account', fn ($q) => $q->whereIn('organization_id', $orgArray));
            $recentShares->whereHas('account', fn ($q) => $q->whereIn('organization_id', $orgArray));
            $recentWelfare->whereHas('account', fn ($q) => $q->whereIn('organization_id', $orgArray));
        }

        return $recentSavings->latest()->take(5)->get()
            ->map(fn ($t) => ['type' => 'savings', 'label' => 'Savings ' . $t->transaction_type, 'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name, 'amount' => $t->amount, 'date' => $t->created_at, 'icon' => 'bi-wallet2', 'color' => $t->transaction_type === 'deposit' ? 'success' : 'danger'])
            ->concat($recentShares->latest()->take(5)->get()
                ->map(fn ($t) => ['type' => 'shares', 'label' => 'Share ' . $t->transaction_type, 'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name, 'amount' => $t->amount, 'date' => $t->created_at, 'icon' => 'bi-cash-stack', 'color' => 'primary']))
            ->concat($recentWelfare->latest()->take(5)->get()
                ->map(fn ($t) => ['type' => 'welfare', 'label' => 'Welfare ' . $t->transaction_type, 'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name, 'amount' => $t->amount, 'date' => $t->created_at, 'icon' => 'bi-heart', 'color' => 'info']))
            ->sortByDesc('date')->take(10)->values();
    }

    private function getStaffScopeLabel(): string
    {
        $branches = $this->user->branches()->pluck('branches.name');
        if ($branches->isNotEmpty()) {
            return 'Branch: ' . $branches->first();
        }
        $orgs = $this->user->organizations()->pluck('organizations.name');
        if ($orgs->isNotEmpty()) {
            return 'Organization: ' . $orgs->first();
        }
        return 'Staff';
    }
}
