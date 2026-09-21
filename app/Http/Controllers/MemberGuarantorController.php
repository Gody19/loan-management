<?php

namespace App\Http\Controllers;

use App\Models\LoanApplicationGuarantor;
use App\Services\LoanApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberGuarantorController extends Controller
{
    public function __construct(
        private LoanApplicationService $applicationService
    ) {}

    public function index(Request $request): View
    {
        $member = $request->user()->member;

        $guarantorRequests = $member->guarantorRequests()
            ->with([
                'application.loanPlan',
                'application.member',
            ])
            ->latest()
            ->get();

        return view('member.guarantor-requests.index', compact('member', 'guarantorRequests'));
    }

    public function show(Request $request, LoanApplicationGuarantor $guarantor): View
    {
        $member = $request->user()->member;

        if ($guarantor->guarantor_member_id !== $member->id) {
            abort(404);
        }

        $guarantor->load([
            'application.loanPlan',
            'application.member',
            'application.guarantors.guarantorMember',
            'application.collaterals',
        ]);

        return view('member.guarantor-requests.show', compact('member', 'guarantor'));
    }

    public function accept(Request $request, LoanApplicationGuarantor $guarantor): RedirectResponse
    {
        $member = $request->user()->member;

        if ($guarantor->guarantor_member_id !== $member->id) {
            abort(404);
        }

        $validated = $request->validate([
            'nida_number' => 'required|string|min:6|max:50',
        ]);

        try {
            $this->applicationService->respondToGuarantor($guarantor, true, null, $validated['nida_number'], true);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('member.guarantor.request', $guarantor)
            ->with('success', 'Guarantor request accepted successfully.');
    }

    public function reject(Request $request, LoanApplicationGuarantor $guarantor): RedirectResponse
    {
        $member = $request->user()->member;

        if ($guarantor->guarantor_member_id !== $member->id) {
            abort(404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            $this->applicationService->respondToGuarantor($guarantor, false, $validated['rejection_reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('member.guarantor.request', $guarantor)
            ->with('success', 'Guarantor request rejected.');
    }
}
