<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $loans = $user->loans()->latest()->paginate(10);

        return view('dashboard.user.loanhistory', compact('loans'));
    }

    public function create()
    {
        return view('dashboard.user.applyloan');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'purpose' => 'required|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'tenure_months' => 'required|integer|min:1|max:360',
        ]);

        $user = auth()->user();
        $previousApproved = $user->loans()->where('status', 'approved')->orderBy('created_at')->get();

        if ($previousApproved->isEmpty()) {
            if ($validated['amount'] > 1_000_000) {
                return back()->withErrors(['amount' => 'First-time applicants can only apply for up to TZS 1,000,000.'])->withInput();
            }
        } else {
            $firstLoan = $previousApproved->first();
            $requiredPaid = $firstLoan->total_payable * 0.75;
            if ($firstLoan->amount_paid < $requiredPaid) {
                return back()->withErrors(['amount' => 'You must repay at least 75% of your current loan before applying for a new one.'])->withInput();
            }
        }

        $validated['user_id'] = $user->id;
        $validated['status'] = 'pending';

        Loan::create($validated);

        return redirect()->route('loans.index')->with('success', 'Loan application submitted successfully.');
    }

    public function show(Loan $loan)
    {
        $this->authorizeView($loan);

        return view('dashboard.user.loan', compact('loan'));
    }

    public function userHistory()
    {
        $user = auth()->user();
        $loans = $user->loans()->with('repayments')->latest()->paginate(10);

        return view('dashboard.user.my-history', compact('loans'));
    }

    public function myHistory()
    {
        $this->authorizeAdmin();

        $loans = Loan::with('user')
            ->where('processed_by', auth()->id())
            ->whereIn('status', ['approved', 'rejected'])
            ->latest('updated_at')
            ->paginate(20);

        $summary = [
            'approved' => Loan::where('processed_by', auth()->id())->where('status', 'approved')->count(),
            'rejected' => Loan::where('processed_by', auth()->id())->where('status', 'rejected')->count(),
            'approved_amount' => Loan::where('processed_by', auth()->id())->where('status', 'approved')->sum('amount'),
            'rejected_amount' => Loan::where('processed_by', auth()->id())->where('status', 'rejected')->sum('amount'),
        ];

        return view('dashboard.admin.loans.my-history', compact('loans', 'summary'));
    }

    public function pending()
    {
        $this->authorizeAdmin();

        $loans = Loan::where('status', 'pending')
            ->with('user')
            ->latest()
            ->paginate(20);

        return view('dashboard.admin.loans.pending', compact('loans'));
    }

    public function approve(Loan $loan)
    {
        $this->authorizeAdmin();

        DB::transaction(function () use ($loan) {
            $interestRate = 8.5;
            $totalPayable = $loan->amount * (1 + ($interestRate / 100) * ($loan->tenure_months / 12));
            $repaymentAmount = $totalPayable / $loan->tenure_months;

            $loan->update([
                'status' => 'approved',
                'interest_rate' => $interestRate,
                'total_payable' => round($totalPayable, 2),
                'repayment_amount' => round($repaymentAmount, 2),
                'approved_at' => now(),
                'processed_by' => auth()->id(),
            ]);

            $this->generateRepaymentSchedule($loan);
        });

        return redirect()->route('loans.pending')->with('success', 'Loan approved successfully.');
    }

    public function reject(Request $request, Loan $loan)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $loan->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'processed_by' => auth()->id(),
            'description' => $validated['reason'] ?? $loan->description,
        ]);

        return redirect()->route('loans.pending')->with('success', 'Loan rejected.');
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }

    private function authorizeView(Loan $loan): void
    {
        $user = auth()->user();
        if ($user->role !== 'admin' && $user->role !== 'manager' && $user->role !== 'loan_officer' && $loan->user_id !== $user->id) {
            abort(403);
        }
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin', 'manager', 'loan_officer'])) {
            abort(403);
        }
    }

    private function generateRepaymentSchedule(Loan $loan): void
    {
        $installments = [];
        $dueDate = now()->addMonth();

        for ($i = 1; $i <= $loan->tenure_months; $i++) {
            $installments[] = [
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'amount' => $loan->repayment_amount,
                'paid_amount' => 0,
                'due_date' => $dueDate->copy()->startOfDay(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $dueDate->addMonth();
        }

        $loan->repayments()->insert($installments);
    }
}
