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
        return [
            'dashboard_type' => 'super_admin',
            'title' => 'Platform Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => 'Platform Administration',
            'widgets' => [
                'organizations' => [
                    'total' => Organization::count(),
                    'active' => Organization::active()->count(),
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
        $orgIds = $this->user->organizations()->pluck('organizations.id')->toArray();

        $memberQuery = $orgIds ? Member::whereIn('organization_id', $orgIds) : Member::whereRaw('1 = 0');
        $savingsQuery = $orgIds ? SavingsAccount::whereIn('organization_id', $orgIds) : SavingsAccount::whereRaw('1 = 0');
        $shareQuery = $orgIds ? ShareAccount::whereIn('organization_id', $orgIds) : ShareAccount::whereRaw('1 = 0');
        $welfareQuery = $orgIds ? WelfareAccount::whereIn('organization_id', $orgIds) : WelfareAccount::whereRaw('1 = 0');
        $loanQuery = $orgIds ? LoanApplication::whereIn('organization_id', $orgIds) : LoanApplication::whereRaw('1 = 0');

        $branchIds = $orgIds ? Branch::whereIn('organization_id', $orgIds)->pluck('id')->toArray() : [];

        return [
            'dashboard_type' => 'org_admin',
            'title' => 'Organization Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => $this->user->organizations()->pluck('organizations.name')->first() ?? 'Organization',
            'widgets' => [
                'organization' => [
                    'branches' => $orgIds ? Branch::whereIn('organization_id', $orgIds)->count() : 0,
                    'groups' => $branchIds ? VicobaGroup::whereIn('branch_id', $branchIds)->count() : 0,
                ],
                'members' => [
                    'total' => $memberQuery->count(),
                    'active' => (clone $memberQuery)->where('membership_status', 'active')->count(),
                    'pending' => (clone $memberQuery)->where('membership_status', 'pending')->count(),
                    'suspended' => (clone $memberQuery)->where('membership_status', 'suspended')->count(),
                ],
                'savings' => [
                    'accounts' => $savingsQuery->where('status', 'active')->count(),
                    'total_balance' => (clone $savingsQuery)->where('status', 'active')->sum('current_balance'),
                ],
                'shares' => [
                    'accounts' => $shareQuery->where('status', 'active')->count(),
                    'total_shares' => (clone $shareQuery)->where('status', 'active')->sum('total_shares'),
                    'total_value' => (clone $shareQuery)->where('status', 'active')->sum('total_value'),
                ],
                'welfare' => [
                    'accounts' => $welfareQuery->where('status', 'active')->count(),
                    'total_balance' => (clone $welfareQuery)->where('status', 'active')->sum('current_balance'),
                ],
                'loans' => [
                    'plans' => $orgIds ? LoanPlan::whereIn('organization_id', $orgIds)->count() : 0,
                    'applications' => $loanQuery->count(),
                    'pending' => (clone $loanQuery)->where('status', 'submitted')->count(),
                    'under_review' => (clone $loanQuery)->where('status', 'under_review')->count(),
                    'approved' => (clone $loanQuery)->where('status', 'approved')->count(),
                    'rejected' => (clone $loanQuery)->where('status', 'rejected')->count(),
                ],
            ],
            'recent_activity' => $this->getRecentActivity($orgIds),
        ];
    }

    private function branchManagerDashboard(): array
    {
        $branchIds = $this->user->branches()->pluck('branches.id')->toArray();

        $memberQuery = $branchIds ? Member::whereIn('branch_id', $branchIds) : Member::whereRaw('1 = 0');
        $savingsQuery = $branchIds ? SavingsAccount::whereIn('branch_id', $branchIds) : SavingsAccount::whereRaw('1 = 0');
        $shareQuery = $branchIds ? ShareAccount::whereIn('branch_id', $branchIds) : ShareAccount::whereRaw('1 = 0');
        $welfareQuery = $branchIds ? WelfareAccount::whereIn('branch_id', $branchIds) : WelfareAccount::whereRaw('1 = 0');
        $loanQuery = $branchIds ? LoanApplication::whereIn('branch_id', $branchIds) : LoanApplication::whereRaw('1 = 0');

        return [
            'dashboard_type' => 'branch_manager',
            'title' => 'Branch Dashboard',
            'welcome' => 'Welcome back, ' . $this->user->fullname,
            'scope_label' => 'Branch: ' . ($this->user->branches()->pluck('branches.name')->first() ?? 'Unassigned'),
            'widgets' => [
                'branch' => [
                    'groups' => $branchIds ? VicobaGroup::whereIn('branch_id', $branchIds)->count() : 0,
                ],
                'members' => [
                    'total' => $memberQuery->count(),
                    'active' => (clone $memberQuery)->where('membership_status', 'active')->count(),
                    'pending' => (clone $memberQuery)->where('membership_status', 'pending')->count(),
                ],
                'savings' => [
                    'accounts' => $savingsQuery->where('status', 'active')->count(),
                    'total_balance' => (clone $savingsQuery)->where('status', 'active')->sum('current_balance'),
                ],
                'shares' => [
                    'total_shares' => $shareQuery->where('status', 'active')->sum('total_shares'),
                    'total_value' => (clone $shareQuery)->where('status', 'active')->sum('total_value'),
                ],
                'welfare' => [
                    'total_balance' => $welfareQuery->where('status', 'active')->sum('current_balance'),
                ],
                'loans' => [
                    'applications' => $loanQuery->count(),
                    'pending' => (clone $loanQuery)->where('status', 'submitted')->count(),
                    'under_review' => (clone $loanQuery)->where('status', 'under_review')->count(),
                    'approved' => (clone $loanQuery)->where('status', 'approved')->count(),
                ],
            ],
            'recent_activity' => $this->getRecentActivity([], $branchIds),
        ];
    }

    private function staffDashboard(): array
    {
        $orgIds = $this->user->organizations()->pluck('organizations.id')->toArray();
        $branchIds = $this->user->branches()->pluck('branches.id')->toArray();
        $hasOrg = !empty($orgIds);
        $hasBranch = !empty($branchIds);

        $widgets = [];

        if ($this->user->can('member.view')) {
            $q = Member::query();
            if ($hasBranch) {
                $q->whereIn('branch_id', $branchIds);
            } elseif ($hasOrg) {
                $q->whereIn('organization_id', $orgIds);
            }

            $widgets['members'] = [
                'total' => $q->count(),
                'active' => (clone $q)->where('membership_status', 'active')->count(),
            ];
        }

        if ($this->user->can('savings_account.view')) {
            $q = SavingsAccount::query();
            if ($hasBranch) {
                $q->whereIn('branch_id', $branchIds);
            } elseif ($hasOrg) {
                $q->whereIn('organization_id', $orgIds);
            }

            $widgets['savings'] = [
                'accounts' => $q->where('status', 'active')->count(),
                'total_balance' => (clone $q)->where('status', 'active')->sum('current_balance'),
            ];
        }

        if ($this->user->can('share_account.view')) {
            $q = ShareAccount::query();
            if ($hasBranch) {
                $q->whereIn('branch_id', $branchIds);
            } elseif ($hasOrg) {
                $q->whereIn('organization_id', $orgIds);
            }

            $widgets['shares'] = [
                'total_shares' => $q->where('status', 'active')->sum('total_shares'),
                'total_value' => $q->where('status', 'active')->sum('total_value'),
            ];
        }

        if ($this->user->can('welfare_account.view')) {
            $q = WelfareAccount::query();
            if ($hasBranch) {
                $q->whereIn('branch_id', $branchIds);
            } elseif ($hasOrg) {
                $q->whereIn('organization_id', $orgIds);
            }

            $widgets['welfare'] = [
                'total_balance' => $q->where('status', 'active')->sum('current_balance'),
            ];
        }

        if ($this->user->can('loan_application.view')) {
            $q = LoanApplication::query();
            if ($hasBranch) {
                $q->whereIn('branch_id', $branchIds);
            } elseif ($hasOrg) {
                $q->whereIn('organization_id', $orgIds);
            }

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
                    ->map(fn ($t) => ['type' => 'savings', 'label' => 'Savings ' . $t->transaction_type->label(), 'amount' => $t->amount, 'date' => $t->created_at])
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

    private function getRecentActivity(?array $orgIds = null, ?array $branchIds = null): \Illuminate\Support\Collection
    {
        $recentSavings = SavingsTransaction::with('account.member')
            ->where('status', 'completed');
        $recentShares = ShareTransaction::with('account.member')
            ->where('status', 'completed');
        $recentWelfare = WelfareTransaction::with('account.member')
            ->where('status', 'completed');

        if ($branchIds) {
            $recentSavings->whereHas('account', fn ($q) => $q->whereIn('branch_id', $branchIds));
            $recentShares->whereHas('account', fn ($q) => $q->whereIn('branch_id', $branchIds));
            $recentWelfare->whereHas('account', fn ($q) => $q->whereIn('branch_id', $branchIds));
        } elseif ($orgIds) {
            $recentSavings->whereHas('account', fn ($q) => $q->whereIn('organization_id', $orgIds));
            $recentShares->whereHas('account', fn ($q) => $q->whereIn('organization_id', $orgIds));
            $recentWelfare->whereHas('account', fn ($q) => $q->whereIn('organization_id', $orgIds));
        }

        return $recentSavings->latest()->take(5)->get()
            ->map(fn ($t) => ['type' => 'savings', 'label' => 'Savings ' . $t->transaction_type->label(), 'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name, 'amount' => $t->amount, 'date' => $t->created_at, 'icon' => 'bi-wallet2', 'color' => $t->transaction_type->isCredit() ? 'success' : 'danger'])
            ->concat($recentShares->latest()->take(5)->get()
                ->map(fn ($t) => ['type' => 'shares', 'label' => 'Share ' . $t->transaction_type->label(), 'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name, 'amount' => $t->amount, 'date' => $t->created_at, 'icon' => 'bi-cash-stack', 'color' => 'primary']))
            ->concat($recentWelfare->latest()->take(5)->get()
                ->map(fn ($t) => ['type' => 'welfare', 'label' => 'Welfare ' . $t->transaction_type->label(), 'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name, 'amount' => $t->amount, 'date' => $t->created_at, 'icon' => 'bi-heart', 'color' => 'info']))
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
