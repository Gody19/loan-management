<?php

namespace App\Http\Controllers;

class UserDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $loansCount = $user->loans()->where('status', 'pending')->count();
        $totalLoans = $user->loans()->count();
        $portfolios = $user->portfolios()->get();
        $budgets = $user->budgets()->where('status', 'active')->get();
        $recentTransactions = $user->transactions()->latest()->take(5)->get();

        return view('dashboard.user.index', [
            'loansCount' => $loansCount,
            'totalLoans' => $totalLoans,
            'portfolios' => $portfolios,
            'budgets' => $budgets,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
