<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $totalLoans = Loan::count();
        $pendingLoans = Loan::where('status', 'pending')->count();
        $approvedLoans = Loan::where('status', 'approved')->count();
        $totalLoanValue = Loan::sum('amount');

        $totalCollected = Repayment::where('status', 'paid')->sum('paid_amount');
        $pendingPayments = Repayment::where('status', 'pending')->count();
        $overduePayments = Repayment::where('due_date', '<', now())
            ->where('status', 'pending')
            ->count();
        $todayPayments = Repayment::where('status', 'paid')
            ->whereDate('paid_date', today())
            ->sum('paid_amount');

        $totalPayable = Loan::where('status', 'approved')->sum('total_payable');
        $collectionRate = $totalPayable > 0 ? round(($totalCollected / $totalPayable) * 100, 1) : 0;

        $recentPayments = Repayment::with('loan.user')
            ->where('status', 'paid')
            ->latest('paid_date')
            ->take(5)
            ->get();

        return view('dashboard.admin.index', [
            'totalUsers' => $totalUsers,
            'totalLoans' => $totalLoans,
            'pendingLoans' => $pendingLoans,
            'approvedLoans' => $approvedLoans,
            'totalLoanValue' => $totalLoanValue,
            'totalCollected' => $totalCollected,
            'pendingPayments' => $pendingPayments,
            'overduePayments' => $overduePayments,
            'todayPayments' => $todayPayments,
            'collectionRate' => $collectionRate,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function users()
    {
        $users = User::latest()->paginate(20);

        return view('dashboard.admin.users.index', compact('users'));
    }

    public function allProcessed(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $loans = $query->orderBy('updated_at', 'desc')->paginate(20)->withQueryString();

        $processors = User::whereIn('role', ['admin', 'manager', 'loan_officer'])
            ->where('is_active', true)
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'role']);

        $processorStats = User::whereIn('role', ['admin', 'manager', 'loan_officer'])
            ->where('is_active', true)
            ->withCount(['processedLoans as approved_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->withCount(['processedLoans as rejected_count' => function ($q) {
                $q->where('status', 'rejected');
            }])
            ->withCount(['processedLoans as total_processed' => function ($q) {
                $q->whereIn('status', ['approved', 'rejected']);
            }])
            ->get()
            ->map(function ($p) {
                $p->approved_amount = Loan::where('processed_by', $p->id)->where('status', 'approved')->sum('amount');
                $p->rejected_amount = Loan::where('processed_by', $p->id)->where('status', 'rejected')->sum('amount');

                return $p;
            });

        $totals = [
            'approved' => Loan::where('status', 'approved')->count(),
            'rejected' => Loan::where('status', 'rejected')->count(),
            'approved_amount' => Loan::where('status', 'approved')->sum('amount'),
            'rejected_amount' => Loan::where('status', 'rejected')->sum('amount'),
            'staff' => $processors->count(),
        ];

        return view('dashboard.admin.all-processed', compact('loans', 'processors', 'processorStats', 'totals'));
    }
}
