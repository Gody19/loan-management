<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuarantorRequest;
use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;
use App\Services\GuarantorEligibilityService;
use App\Services\LoanApplicationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanApplicationGuarantorController extends Controller
{
    public function __construct(
        private LoanApplicationService $loanAppService,
        private GuarantorEligibilityService $eligibilityService
    ) {}

    public function store(StoreGuarantorRequest $request, LoanApplication $loanApplication)
    {
        $this->authorize('manageGuarantors', $loanApplication);

        $guarantorMember = Member::findOrFail($request->guarantor_member_id);

        $eligibility = $this->eligibilityService->canGuarantee($guarantorMember, $loanApplication);
        if (!$eligibility['eligible']) {
            return back()->with('error', $eligibility['reason']);
        }

        $existingCount = $loanApplication->guarantors()
            ->where('status', '!=', \App\Enums\GuarantorStatus::Rejected)
            ->count();

        if ($existingCount >= 2) {
            return back()->with('error', 'Maximum 2 guarantors allowed per application.');
        }

        try {
            $guarantor = $this->loanAppService->addGuarantor($loanApplication, $request->validated(), $guarantorMember);
            return back()->with('success', 'Guarantor added.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(LoanApplication $loanApplication, LoanApplicationGuarantor $guarantor)
    {
        $this->authorize('manageGuarantors', $loanApplication);

        try {
            $this->loanAppService->removeGuarantor($guarantor);
            return back()->with('success', 'Guarantor removed.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function respond(LoanApplicationGuarantor $guarantor, Request $request)
    {
        $this->authorize('respond', $guarantor);

        $request->validate([
            'accept' => 'required|boolean',
            'reason' => 'required_if:accept,false|nullable|string|max:1000',
        ]);

        try {
            $this->loanAppService->respondToGuarantor($guarantor, $request->boolean('accept'), $request->reason);
            return back()->with('success', $request->boolean('accept') ? 'Guarantee accepted.' : 'Guarantee rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reviewQueue(Request $request): View
    {
        $this->authorize('viewAny', LoanApplicationGuarantor::class);

        $pendingGuarantors = LoanApplicationGuarantor::query()
            ->where('status', \App\Enums\GuarantorStatus::Pending)
            ->whereHas('application', function ($q) {
                $q->whereIn('status', ['submitted', 'under_review']);
            })
            ->with([
                'application.loanPlan',
                'application.member',
                'guarantorMember',
            ])
            ->latest()
            ->get();

        return view('admin.guarantor-reviews.index', compact('pendingGuarantors'));
    }

    public function approve(LoanApplicationGuarantor $guarantor)
    {
        $this->authorize('manageGuarantors', $guarantor->application);

        if ($guarantor->status !== \App\Enums\GuarantorStatus::Pending) {
            return back()->with('error', 'This guarantor has already been reviewed.');
        }

        try {
            $this->loanAppService->respondToGuarantor($guarantor, true);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Guarantor approved successfully.');
    }

    public function rejectGuarantor(LoanApplicationGuarantor $guarantor, Request $request)
    {
        $this->authorize('manageGuarantors', $guarantor->application);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($guarantor->status !== \App\Enums\GuarantorStatus::Pending) {
            return back()->with('error', 'This guarantor has already been reviewed.');
        }

        try {
            $this->loanAppService->respondToGuarantor($guarantor, false, $validated['rejection_reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Guarantor rejected.');
    }
}
