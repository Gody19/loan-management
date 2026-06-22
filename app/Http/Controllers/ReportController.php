<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Loan::with(['user', 'processor'])
            ->whereIn('status', ['approved', 'rejected'])
            ->whereNotNull('processed_by');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('processor_id')) {
            $query->where('processed_by', $request->processor_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $loans = $query->orderBy('created_at', 'desc')->paginate(15);

        $summary = Loan::with('processor')
            ->whereIn('status', ['approved', 'rejected'])
            ->whereNotNull('processed_by')
            ->select('processed_by', 'status', DB::raw('count(*) as total'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('processed_by', 'status')
            ->get()
            ->groupBy('processed_by');

        $processors = User::whereIn('role', ['admin', 'manager', 'loan_officer'])
            ->where('is_active', true)
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'role']);

        $totals = [
            'approved' => Loan::where('status', 'approved')->count(),
            'rejected' => Loan::where('status', 'rejected')->count(),
            'approved_amount' => Loan::where('status', 'approved')->sum('amount'),
            'rejected_amount' => Loan::where('status', 'rejected')->sum('amount'),
        ];

        return view('dashboard.admin.reports.index', compact('loans', 'summary', 'processors', 'totals'));
    }
}
