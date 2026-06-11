<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $totalUsers = \App\Models\User::count();
        $totalLoans = \App\Models\Loan::count();
        $pendingLoans = \App\Models\Loan::where('status', 'pending')->count();
        $approvedLoans = \App\Models\Loan::where('status', 'approved')->count();
        $totalLoanValue = \App\Models\Loan::sum('amount');

        return view('dashboard.admin.index', [
            'totalUsers' => $totalUsers,
            'totalLoans' => $totalLoans,
            'pendingLoans' => $pendingLoans,
            'approvedLoans' => $approvedLoans,
            'totalLoanValue' => $totalLoanValue,
        ]);
    }
}
