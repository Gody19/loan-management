<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareTransaction;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $data = $this->getDashboardData($user);

        return view('dashboard.index', compact('data'));
    }

    private function getDashboardData($user)
    {
        // Members
        $totalMembers = Member::count();
        $activeMembers = Member::where('membership_status', 'active')->count();
        $newMembersThisMonth = Member::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Groups
        $activeGroups = VicobaGroup::where('status', 'active')->count();

        // Savings
        $totalSavings = SavingsAccount::where('status', 'active')->sum('current_balance');
        $totalDepositThisMonth = SavingsTransaction::where('transaction_type', 'deposit')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $totalWithdrawalThisMonth = SavingsTransaction::where('transaction_type', 'withdrawal')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // Shares
        $totalShares = ShareAccount::where('status', 'active')->sum('total_shares');
        $totalShareValue = ShareAccount::where('status', 'active')->sum('total_value');

        // Welfare
        $totalWelfareBalance = WelfareAccount::where('status', 'active')->sum('current_balance');
        $totalWelfareContributions = WelfareTransaction::where('transaction_type', 'contribution')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // Recent transactions
        $recentSavings = SavingsTransaction::with('account.member')
            ->where('status', 'completed')
            ->latest()
            ->take(10)
            ->get();

        $recentShares = ShareTransaction::with('account.member')
            ->where('status', 'completed')
            ->latest()
            ->take(10)
            ->get();

        $recentWelfare = WelfareTransaction::with('account.member')
            ->where('status', 'completed')
            ->latest()
            ->take(10)
            ->get();

        // Merge and sort recent activity
        $recentActivity = $recentSavings->map(fn ($t) => [
            'type' => 'savings',
            'label' => 'Savings ' . $t->transaction_type,
            'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name,
            'amount' => $t->amount,
            'date' => $t->created_at,
            'icon' => 'bi-wallet2',
            'color' => $t->transaction_type === 'deposit' ? 'success' : 'danger',
        ])
        ->concat($recentShares->map(fn ($t) => [
            'type' => 'shares',
            'label' => 'Share ' . $t->transaction_type,
            'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name,
            'amount' => $t->amount,
            'date' => $t->created_at,
            'icon' => 'bi-cash-stack',
            'color' => $t->transaction_type === 'purchase' ? 'primary' : 'warning',
        ]))
        ->concat($recentWelfare->map(fn ($t) => [
            'type' => 'welfare',
            'label' => 'Welfare ' . $t->transaction_type,
            'member' => $t->account->member->first_name . ' ' . $t->account->member->last_name,
            'amount' => $t->amount,
            'date' => $t->created_at,
            'icon' => 'bi-heart',
            'color' => $t->transaction_type === 'contribution' ? 'info' : 'warning',
        ]))
        ->sortByDesc('date')
        ->take(15)
        ->values();

        return compact(
            'totalMembers',
            'activeMembers',
            'newMembersThisMonth',
            'activeGroups',
            'totalSavings',
            'totalDepositThisMonth',
            'totalWithdrawalThisMonth',
            'totalShares',
            'totalShareValue',
            'totalWelfareBalance',
            'totalWelfareContributions',
            'recentActivity',
        );
    }
}
