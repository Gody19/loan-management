<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmDisbursementRequest;
use App\Http\Requests\StoreDisbursementRequest;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Services\LoanDisbursementService;
use Illuminate\Http\Request;

class LoanDisbursementController extends Controller
{
    public function __construct(
        private LoanDisbursementService $disbursementService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LoanDisbursement::class);

        $user = $request->user();

        if ($user->hasRole('Super Administrator')) {
            $query = LoanDisbursement::with(['loan', 'paymentMethod', 'processor']);
        } else {
            $orgIds = $user->organizations()->pluck('organizations.id')->toArray();
            if (!empty($orgIds)) {
                $query = LoanDisbursement::whereIn('organization_id', $orgIds)
                    ->with(['loan', 'paymentMethod', 'processor']);
            } else {
                $query = LoanDisbursement::whereRaw('1 = 0');
            }
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        $disbursements = $query->latest()->paginate(15)->withQueryString();

        return view('loans.disbursements.index', compact('disbursements'));
    }

    public function store(StoreDisbursementRequest $request)
    {
        $loan = Loan::findOrFail($request->loan_id);
        $this->authorize('disburse', $loan);

        try {
            $disbursement = $this->disbursementService->createDisbursementRecord(
                $loan,
                $request->amount,
                $request->disbursement_method,
                $request->payment_method_id,
                $request->reference_number,
                $request->notes,
            );

            return redirect()->route('loans.show', $loan)
                ->with('success', 'Disbursement record ' . $disbursement->disbursement_number . ' created.');
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(LoanDisbursement $disbursement)
    {
        $this->authorize('view', $disbursement);

        $disbursement->load(['loan.member', 'loan.loanPlan', 'paymentMethod', 'processor']);

        return view('loans.disbursements.show', compact('disbursement'));
    }

    public function confirm(LoanDisbursement $disbursement, ConfirmDisbursementRequest $request)
    {
        $this->authorize('confirm', $disbursement);

        try {
            $this->disbursementService->confirmDisbursement($disbursement, $request->user());
            return redirect()->route('loans.show', $disbursement->loan)
                ->with('success', 'Disbursement confirmed. Loan is now active.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(LoanDisbursement $disbursement, Request $request)
    {
        $this->authorize('reject', $disbursement);

        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->disbursementService->rejectDisbursement($disbursement, $request->reason, $request->user());
            return redirect()->route('loans.show', $disbursement->loan)
                ->with('success', 'Disbursement rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
