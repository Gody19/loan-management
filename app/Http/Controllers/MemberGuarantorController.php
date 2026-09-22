<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;
use App\Services\GuarantorEligibilityService;
use App\Services\LoanApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberGuarantorController extends Controller
{
    public function __construct(
        private LoanApplicationService $applicationService,
        private GuarantorEligibilityService $eligibilityService
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

        if ($guarantor->status !== \App\Enums\GuarantorStatus::Pending) {
            return back()->withErrors(['error' => 'This guarantor request has already been processed.']);
        }

        $validated = $request->validate([
            'guaranteed_amount' => 'required|numeric|min:1',
        ]);

        // Check eligibility before accepting
        $eligibility = $this->eligibilityService->canGuarantee($member, $guarantor->application, $guarantor->id);
        if (!$eligibility['eligible']) {
            return back()->withErrors(['error' => $eligibility['reason']]);
        }

        $guarantor->update([
            'status' => \App\Enums\GuarantorStatus::Accepted,
            'guaranteed_amount' => $validated['guaranteed_amount'],
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()->id,
        ]);

        return redirect()->route('member.guarantor.request', $guarantor)
            ->with('success', 'Guarantor request confirmed successfully.');
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

    public function searchMembers(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        $query = $request->input('q', '');
        $applicationId = $request->input('application_id');

        $application = $applicationId ? LoanApplication::find($applicationId) : null;

        $results = Member::query()
            ->where('organization_id', $member->organization_id)
            ->where('id', '!=', $member->id)
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                  ->orWhere('last_name', 'LIKE', "%{$query}%")
                  ->orWhere('member_number', 'LIKE', "%{$query}%")
                  ->orWhere('national_id', 'LIKE', "%{$query}%")
                  ->orWhere('phone', 'LIKE', "%{$query}%");
            })
            ->with('vicobaGroup:id,name')
            ->limit(20)
            ->get()
            ->map(function ($m) use ($application) {
                $eligibility = $this->eligibilityService->getEligibility($m, $application);
                return [
                    'id' => $m->id,
                    'full_name' => $m->full_name,
                    'member_number' => $m->member_number,
                    'national_id' => $m->national_id,
                    'phone' => $m->phone,
                    'group' => $m->vicobaGroup->name ?? '—',
                    'eligible' => $eligibility['eligible'],
                    'reason' => $eligibility['reason'],
                    'active_guarantees' => $eligibility['active_count'],
                ];
            });

        return response()->json($results);
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

        $eligibility = $this->eligibilityService->canGuarantee($member, $application);
        if (!$eligibility['eligible']) {
            return back()->withErrors(['error' => $eligibility['reason']]);
        }

        $guarantor = $this->applicationService->addGuarantor($application, [
            'guaranteed_amount' => $validated['guaranteed_amount'],
            'notes' => $validated['notes'] ?? null,
        ], $member);

        return redirect()->route('member.guarantor.request', $guarantor)
            ->with('success', 'You have successfully offered as a guarantor. The applicant will be notified.');
    }

    public function myGuarantees(Request $request): View
    {
        $member = $request->user()->member;

        $activeGuarantees = LoanApplicationGuarantor::where('guarantor_member_id', $member->id)
            ->whereIn('status', [\App\Enums\GuarantorStatus::Pending, \App\Enums\GuarantorStatus::Accepted])
            ->with([
                'application.loan',
                'application.member',
                'application.loanPlan',
            ])
            ->get();

        $completedGuarantees = LoanApplicationGuarantor::where('guarantor_member_id', $member->id)
            ->where('status', \App\Enums\GuarantorStatus::Accepted)
            ->whereHas('application.loan', function ($q) {
                $q->whereIn('status', [\App\Enums\LoanStatus::Completed, \App\Enums\LoanStatus::Cancelled]);
            })
            ->with([
                'application.loan',
                'application.member',
                'application.loanPlan',
            ])
            ->get();

        $eligibility = $this->eligibilityService->getEligibility($member);

        $myApplications = LoanApplication::where('member_id', $member->id)
            ->whereIn('status', ['draft', 'submitted', 'under_review'])
            ->with(['loanPlan', 'guarantors.guarantorMember'])
            ->latest()
            ->get();

        $searchableMembers = Member::where('organization_id', $member->organization_id)
            ->where('id', '!=', $member->id)
            ->active()
            ->with('vicobaGroup:id,name')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'full_name' => $m->full_name,
                'member_number' => $m->member_number,
                'national_id' => $m->national_id,
                'phone' => $m->phone,
                'group' => $m->vicobaGroup->name ?? '—',
            ]);

        return view('member.guarantor-requests.my-guarantees', compact(
            'member', 'activeGuarantees', 'completedGuarantees', 'eligibility', 'myApplications', 'searchableMembers'
        ));
    }
}
