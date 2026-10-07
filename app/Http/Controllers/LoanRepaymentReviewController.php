<?php

namespace App\Http\Controllers;

use App\Enums\LoanRepaymentStatus;
use App\Models\LoanRepayment;
use App\Services\LoanRepaymentService;
use App\Services\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanRepaymentReviewController extends Controller
{
    public function __construct(private LoanRepaymentService $repaymentService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LoanRepayment::class);

        $org = OrganizationContext::get();
        $this->authorize('viewAny', LoanRepayment::class);

        $repayments = $this->repaymentService
            ->getPendingRepaymentsForOrganization($org->id, $request->get('search'));

        return view('loan-repayments.review.index', [
            'repayments' => $repayments,
            'search' => $request->get('search'),
        ]);
    }

    public function show(LoanRepayment $loanRepayment): View
    {
        $this->authorize('view', $loanRepayment);

        if ($loanRepayment->status !== LoanRepaymentStatus::Pending) {
            abort(404);
        }

        $loanRepayment->load([
            'loan.member', 'loan.loanPlan', 'loan.branch',
            'receiver', 'paymentMethod',
            'allocations.installment',
        ]);

        return view('loan-repayments.review.show', compact('loanRepayment'));
    }

    public function approve(LoanRepayment $loanRepayment): RedirectResponse
    {
        $this->authorize('update', $loanRepayment);

        if ($loanRepayment->status !== LoanRepaymentStatus::Pending) {
            return back()->with('error', 'Only pending payments can be approved.');
        }

        try {
            $this->repaymentService->approveRepayment($loanRepayment);

            return redirect()->route('loan-repayments.review.index')
                ->with('success', 'Payment approved successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, LoanRepayment $loanRepayment): RedirectResponse
    {
        $this->authorize('update', $loanRepayment);

        if ($loanRepayment->status !== LoanRepaymentStatus::Pending) {
            return back()->with('error', 'Only pending payments can be rejected.');
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->repaymentService->rejectRepayment($loanRepayment, $data['rejection_reason']);

            return redirect()->route('loan-repayments.review.index')
                ->with('success', 'Payment rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
