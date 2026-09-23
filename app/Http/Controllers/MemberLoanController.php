<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberLoanApplicationRequest;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanApplicationGuarantor;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Services\AuditService;
use App\Services\GuarantorEligibilityService;
use App\Services\LoanApplicationService;
use App\Services\LoanEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberLoanController extends Controller
{
    public function __construct(
        private LoanEligibilityService $eligibilityService,
        private LoanApplicationService $applicationService,
        private GuarantorEligibilityService $guarantorEligibilityService,
        private AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $member = $request->user()->member;

        $plans = LoanPlan::active()
            ->where('organization_id', $member->organization_id)
            ->get();

        $applications = $member->loanApplications()
            ->with('loanPlan')
            ->latest()
            ->get();

        $activeLoans = $member->loans()
            ->whereIn('status', ['active', 'disbursed', 'pending_disbursement'])
            ->with('loanPlan')
            ->get();

        $completedLoans = $member->loans()
            ->whereIn('status', ['completed', 'cancelled'])
            ->with('loanPlan')
            ->latest()
            ->get();

        return view('member.loans.index', compact('member', 'plans', 'applications', 'activeLoans', 'completedLoans'));
    }

    public function plan(Request $request, LoanPlan $loanPlan): View
    {
        $member = $request->user()->member;

        if ($loanPlan->organization_id !== $member->organization_id) {
            abort(404);
        }

        $eligibility = $this->eligibilityService->checkEligibility(
            $member,
            $loanPlan,
            (float) $loanPlan->maximum_amount,
            $loanPlan->maximum_term
        );

        return view('member.loans.plan', compact('member', 'loanPlan', 'eligibility'));
    }

    public function eligibility(Request $request, LoanPlan $loanPlan): View
    {
        $member = $request->user()->member;

        if ($loanPlan->organization_id !== $member->organization_id) {
            abort(404);
        }

        $amount = (float) $request->query('amount', $loanPlan->maximum_amount);
        $term = (int) $request->query('term', $loanPlan->maximum_term);

        $result = $this->eligibilityService->checkEligibility(
            $member,
            $loanPlan,
            $amount,
            $term
        );

        return view('member.loans.eligibility', compact('member', 'loanPlan', 'result'));
    }

    public function apply(Request $request, LoanPlan $loanPlan): View
    {
        $member = $request->user()->member;

        if ($loanPlan->organization_id !== $member->organization_id) {
            abort(404);
        }

        $eligibility = $this->eligibilityService->checkEligibility(
            $member,
            $loanPlan,
            (float) $loanPlan->maximum_amount,
            $loanPlan->maximum_term
        );

        $pendingApplication = $member->loanApplications()
            ->where('loan_plan_id', $loanPlan->id)
            ->whereIn('status', ['draft', 'submitted', 'under_review'])
            ->first();

        $activeLoans = $member->loans()
            ->whereIn('status', ['active', 'disbursed'])
            ->get();

        $activeLoanCount = $activeLoans->count();
        $hasUnpaidLoan = false;
        $lowestPaidPercent = 100;

        foreach ($activeLoans as $loan) {
            if ($loan->total_amount > 0) {
                $paidPercent = ($loan->amount_paid / $loan->total_amount) * 100;
                if ($paidPercent < $lowestPaidPercent) {
                    $lowestPaidPercent = round($paidPercent, 1);
                }
                if ($paidPercent < 85) {
                    $hasUnpaidLoan = true;
                }
            }
        }

        return view('member.loans.apply', compact('member', 'loanPlan', 'eligibility', 'pendingApplication', 'activeLoanCount', 'hasUnpaidLoan', 'lowestPaidPercent'));
    }

    public function store(MemberLoanApplicationRequest $request, LoanPlan $loanPlan): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanPlan->organization_id !== $member->organization_id) {
            abort(404);
        }

        if (!$loanPlan->status->value === 'active') {
            return back()->withErrors(['loan_plan' => 'This loan plan is not currently available.'])->withInput();
        }

        $application = $this->applicationService->create(
            [
                'branch_id' => $member->branch_id,
                'vicoba_group_id' => $member->vicoba_group_id,
                'requested_amount' => $request->validated('requested_amount'),
                'requested_term' => $request->validated('requested_term'),
                'repayment_frequency' => $loanPlan->repayment_frequency,
                'loan_purpose' => $request->validated('loan_purpose'),
                'purpose_description' => $request->validated('purpose_description'),
            ],
            $member,
            $loanPlan
        );

        return redirect()->route('member.loans.application', $application)
            ->with('success', 'Your loan application has been saved. Review and submit when ready.');
    }

    public function submitApplication(Request $request, LoanApplication $loanApplication): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        // Run pre-submission checks first
        $submissionErrors = $this->getSubmissionErrors($loanApplication, $member);
        if (!empty($submissionErrors)) {
            $messages = array_map(fn($e) => $e['message'], $submissionErrors);
            return back()->withErrors(['error' => 'Cannot submit application: ' . implode(' | ', $messages)]);
        }

        try {
            $this->applicationService->submit($loanApplication);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('member.loans.application', $loanApplication)
            ->with('success', 'Thank you for applying! Your application has been submitted successfully. Please wait for the organization to review your application.');
    }

    public function applications(Request $request): View
    {
        $member = $request->user()->member;

        $applications = $member->loanApplications()
            ->with('loanPlan')
            ->latest()
            ->get();

        return view('member.loans.applications', compact('member', 'applications'));
    }

    public function application(Request $request, LoanApplication $loanApplication): View
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        $loanApplication->load(['loanPlan', 'guarantors.guarantorMember', 'collaterals', 'approvals.actor', 'collateralSnapshot']);

        $submissionErrors = [];
        if ($loanApplication->status->value === 'draft') {
            $submissionErrors = $this->getSubmissionErrors($loanApplication, $member);
        }

        return view('member.loans.application', compact('member', 'loanApplication', 'submissionErrors'));
    }

    private function getSubmissionErrors(LoanApplication $loanApplication, Member $member): array
    {
        $errors = [];
        $plan = $loanApplication->loanPlan;

        // Check eligibility
        $eligibilityResult = $this->eligibilityService->checkEligibility(
            $member,
            $plan,
            (float) $loanApplication->requested_amount,
            $loanApplication->requested_term
        );

        if (!$eligibilityResult->eligible) {
            foreach ($eligibilityResult->failureReasons as $reason) {
                $errors[] = ['type' => 'eligibility', 'message' => $reason];
            }
        }

        // Check guarantor requirements
        $guarantorCount = $loanApplication->guarantors()
            ->where('status', '!=', \App\Enums\GuarantorStatus::Rejected)
            ->count();

        if ($plan->requires_guarantor) {
            if ($guarantorCount < 1) {
                $errors[] = ['type' => 'guarantor', 'message' => 'This plan requires at least ' . $plan->minimum_guarantors . ' guarantor(s). You have ' . $guarantorCount . '.'];
            }
            if ($guarantorCount > 2) {
                $errors[] = ['type' => 'guarantor', 'message' => 'Maximum 2 guarantors allowed. You have ' . $guarantorCount . '.'];
            }
        }

        // Check if guarantors are accepted
        $pendingGuarantors = $loanApplication->guarantors()
            ->where('status', \App\Enums\GuarantorStatus::Pending)
            ->count();
        if ($pendingGuarantors > 0) {
            $errors[] = ['type' => 'guarantor', 'message' => $pendingGuarantors . ' guarantor(s) have not accepted yet.'];
        }

        // Check collateral requirements
        $collateralService = app(\App\Services\CollateralRequirementService::class);
        $collateralErrors = $collateralService->validateCollateral($loanApplication);
        foreach ($collateralErrors as $msg) {
            $errors[] = ['type' => 'collateral', 'message' => $msg];
        }

        return $errors;
    }

    public function cancelApplication(Request $request, LoanApplication $loanApplication): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        try {
            $this->applicationService->cancel($loanApplication, 'Cancelled by member');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('member.loans.application', $loanApplication)
            ->with('success', 'Your application has been cancelled.');
    }

    public function addGuarantor(Request $request, LoanApplication $loanApplication): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        if (!in_array($loanApplication->status->value, ['draft', 'submitted'])) {
            return back()->withErrors(['error' => 'Cannot add guarantors to an application that is not in draft or submitted status.']);
        }

        $existingCount = $loanApplication->guarantors()
            ->where('status', '!=', \App\Enums\GuarantorStatus::Rejected)
            ->count();

        if ($existingCount >= 2) {
            return back()->withErrors(['error' => 'Maximum 2 guarantors allowed per application.']);
        }

        $validated = $request->validate([
            'guarantor_member_id' => 'required|exists:members,id',
            'guaranteed_amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $guarantorMember = Member::findOrFail($validated['guarantor_member_id']);

        $eligibility = $this->guarantorEligibilityService->canGuarantee($guarantorMember, $loanApplication);
        if (!$eligibility['eligible']) {
            return back()->withErrors(['guarantor_member_id' => $eligibility['reason']]);
        }

        $this->applicationService->addGuarantor($loanApplication, $validated, $guarantorMember);

        return back()->with('success', 'Guarantor added. They will be notified to confirm.');
    }

    public function removeGuarantor(Request $request, LoanApplication $loanApplication, LoanApplicationGuarantor $guarantor): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        if ($guarantor->loan_application_id !== $loanApplication->id) {
            abort(404);
        }

        $this->applicationService->removeGuarantor($guarantor);

        return redirect()->route('member.my-guarantees')
            ->with('success', 'Guarantor has been removed.');
    }

    public function updateGuarantor(Request $request, LoanApplication $loanApplication, LoanApplicationGuarantor $guarantor): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        if ($guarantor->loan_application_id !== $loanApplication->id) {
            abort(404);
        }

        if (!in_array($loanApplication->status->value, ['draft', 'submitted'])) {
            return back()->withErrors(['error' => 'Cannot edit guarantors on an application that is not in draft or submitted status.']);
        }

        $validated = $request->validate([
            'guaranteed_amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->applicationService->updateGuarantor($guarantor, $validated);

        return redirect()->route('member.my-guarantees')
            ->with('success', 'Guarantor updated successfully.');
    }

    public function addCollateral(Request $request, LoanApplication $loanApplication): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        $validated = $request->validate([
            'collateral_type' => 'required|string|in:land,vehicle,equipment,building,jewelry,savings,other',
            'description' => 'required|string|max:500',
            'estimated_value' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:100',
            'ownership_details' => 'nullable|string|max:500',
        ]);

        $this->applicationService->addCollateral($loanApplication, $validated);

        return redirect()->route('member.loans.application', $loanApplication)
            ->with('success', 'Collateral information has been added.');
    }

    public function removeCollateral(Request $request, LoanApplication $loanApplication, LoanApplicationCollateral $collateral): RedirectResponse
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        if ($collateral->loan_application_id !== $loanApplication->id) {
            abort(404);
        }

        $this->applicationService->removeCollateral($collateral);

        return redirect()->route('member.loans.application', $loanApplication)
            ->with('success', 'Collateral has been removed.');
    }

    public function uploadCollateralDocument(Request $request, LoanApplication $loanApplication, LoanApplicationCollateral $collateral)
    {
        $member = $request->user()->member;

        if ($loanApplication->member_id !== $member->id) {
            abort(404);
        }

        if ($collateral->loan_application_id !== $loanApplication->id) {
            abort(404);
        }

        if (!in_array($loanApplication->status->value, ['draft', 'submitted'])) {
            return back()->withErrors(['error' => 'Cannot upload documents for an application that is not editable.']);
        }

        $validated = $request->validate([
            'document_type' => 'required|string|in:' . implode(',', \App\Enums\CollateralDocumentType::values()),
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        try {
            $this->applicationService->addCollateralDocument($collateral, $validated, $request->file('file'));
            return back()->with('success', 'Document uploaded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to upload document: ' . $e->getMessage()]);
        }
    }

    public function removeCollateralDocument(Request $request, \App\Models\CollateralDocument $document)
    {
        $member = $request->user()->member;
        $collateral = $document->collateral;
        $application = $collateral->application;

        if ($application->member_id !== $member->id) {
            abort(404);
        }

        $this->applicationService->removeCollateralDocument($document);

        return back()->with('success', 'Document removed.');
    }

    public function downloadCollateralDocument(Request $request, \App\Models\CollateralDocument $document)
    {
        $member = $request->user()->member;
        $collateral = $document->collateral;
        $application = $collateral->application;

        if ($application->member_id !== $member->id) {
            abort(404);
        }

        if (!\Illuminate\Support\Facades\Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'Document not found.');
        }

        return \Illuminate\Support\Facades\Storage::disk('private')->download($document->file_path, $document->original_filename);
    }
}
