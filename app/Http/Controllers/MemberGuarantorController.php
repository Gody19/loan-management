<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;
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
            'guarantor_name' => 'required|string|max:255',
            'guarantor_phone' => 'required|string|max:50',
            'guarantor_email' => 'nullable|email|max:255',
            'guarantor_relationship' => 'nullable|string|max:100',
            'guarantor_occupation' => 'nullable|string|max:255',
            'guarantor_address' => 'nullable|string|max:500',
            'guaranteed_amount' => 'required|numeric|min:1',
        ]);

        try {
            $this->applicationService->respondToGuarantor(
                $guarantor,
                true,
                null,
                $validated['nida_number'],
                true,
                $validated
            );
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

    public function offerForm(Request $request): View
    {
        $member = $request->user()->member;

        $hasActiveGuarantee = LoanApplicationGuarantor::hasActiveGuarantee($member->id);

        $applications = LoanApplication::query()
            ->where('organization_id', $member->organization_id)
            ->where('member_id', '!=', $member->id)
            ->whereIn('status', ['submitted', 'under_review'])
            ->whereDoesntHave('guarantors', function ($q) use ($member) {
                $q->where('guarantor_member_id', $member->id);
            })
            ->with(['loanPlan', 'member'])
            ->latest()
            ->get();

        return view('member.guarantor-requests.offer', compact('member', 'applications', 'hasActiveGuarantee'));
    }

    public function storeOffer(Request $request): RedirectResponse
    {
        $member = $request->user()->member;

        $validated = $request->validate([
            'loan_application_id' => 'required|exists:loan_applications,id',
            'nida_number' => 'required|string|min:6|max:50',
            'guaranteed_amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $application = LoanApplication::findOrFail($validated['loan_application_id']);

        if ($application->member_id === $member->id) {
            return back()->withErrors(['error' => 'You cannot guarantee your own loan application.']);
        }

        if ($application->organization_id !== $member->organization_id) {
            return back()->withErrors(['error' => 'This application is not in your organization.']);
        }

        if (!in_array($application->status->value, ['submitted', 'under_review'])) {
            return back()->withErrors(['error' => 'This application is no longer accepting guarantors.']);
        }

        $alreadyGuarantor = LoanApplicationGuarantor::where('loan_application_id', $application->id)
            ->where('guarantor_member_id', $member->id)
            ->exists();
        if ($alreadyGuarantor) {
            return back()->withErrors(['error' => 'You are already a guarantor on this application.']);
        }

        $nidaTrimmed = trim($validated['nida_number']);
        $nidaExists = LoanApplicationGuarantor::where('nida_number', $nidaTrimmed)
            ->where('status', '!=', \App\Enums\GuarantorStatus::Rejected)
            ->exists();
        if ($nidaExists) {
            return back()->withErrors(['nida_number' => 'This NIDA number is already registered by another guarantor.']);
        }

        if (LoanApplicationGuarantor::hasActiveGuarantee($member->id)) {
            return back()->withErrors(['error' => 'You cannot guarantee another loan because you still have an active guaranteed loan that has not been fully repaid.']);
        }

        $guarantor = $this->applicationService->addGuarantor($application, [
            'guaranteed_amount' => $validated['guaranteed_amount'],
            'nida_number' => $nidaTrimmed,
            'notes' => $validated['notes'] ?? null,
        ], $member);

        return redirect()->route('member.guarantor.request', $guarantor)
            ->with('success', 'You have successfully offered as a guarantor. The applicant will be notified.');
    }
}
