<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (in_array($user->role, ['admin', 'manager', 'loan_officer'])) {
            return redirect('/dashboard/admin');
        }

        return redirect('/dashboard/user');
    }
}
