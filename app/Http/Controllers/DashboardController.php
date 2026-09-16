<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $service = new DashboardService($request->user());
        $data = $service->resolve();

        return view('dashboard.index', $data);
    }
}
