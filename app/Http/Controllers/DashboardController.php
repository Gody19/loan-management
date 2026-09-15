<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index()
    {
        $data = [
            'totalMembers' => 0,
            'totalSavings' => 0,
            'totalLoans' => 0,
            'pendingApplications' => 0,
            'activeGroups' => 0,
            'totalShares' => 0,
        ];

        return view('dashboard.index', compact('data'));
    }
}
