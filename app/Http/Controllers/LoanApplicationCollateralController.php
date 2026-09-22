<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollateralRequest;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Models\CollateralDocument;
use App\Services\LoanApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LoanApplicationCollateralController extends Controller
{
    public function __construct(private LoanApplicationService $loanAppService) {}

    public function store(StoreCollateralRequest $request, LoanApplication $loanApplication)
    {
        $this->authorize('manageCollateral', $loanApplication);

        $collateral = $this->loanAppService->addCollateral($loanApplication, $request->validated());

        return back()->with('success', 'Collateral added.');
    }

    public function destroy(LoanApplication $loanApplication, LoanApplicationCollateral $collateral)
    {
        $this->authorize('manageCollateral', $loanApplication);

        $this->loanAppService->removeCollateral($collateral);

        return back()->with('success', 'Collateral removed.');
    }

    public function verify(Request $request, LoanApplication $loanApplication, LoanApplicationCollateral $collateral)
    {
        $this->authorize('manageCollateral', $loanApplication);

        $validated = $request->validate([
            'reviewed_value' => 'required|numeric|min:0',
            'valuation_date' => 'nullable|date',
            'valuation_reference' => 'nullable|string|max:100',
            'review_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->loanAppService->verifyCollateral($collateral, $validated);
            return back()->with('success', 'Collateral verified successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, LoanApplication $loanApplication, LoanApplicationCollateral $collateral)
    {
        $this->authorize('manageCollateral', $loanApplication);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            $this->loanAppService->rejectCollateral($collateral, $validated['rejection_reason']);
            return back()->with('success', 'Collateral rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function uploadDocument(Request $request, LoanApplication $loanApplication, LoanApplicationCollateral $collateral)
    {
        $this->authorize('manageCollateral', $loanApplication);

        $validated = $request->validate([
            'document_type' => 'required|string|in:' . implode(',', \App\Enums\CollateralDocumentType::values()),
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        try {
            $this->loanAppService->addCollateralDocument($collateral, $validated, $request->file('file'));
            return back()->with('success', 'Document uploaded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to upload document: ' . $e->getMessage()]);
        }
    }

    public function downloadDocument(CollateralDocument $document)
    {
        $collateral = $document->collateral;
        $application = $collateral->application;

        $this->authorize('manageCollateral', $application);

        if (!Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'Document not found.');
        }

        return Storage::disk('private')->download($document->file_path, $document->original_filename);
    }

    public function destroyDocument(CollateralDocument $document)
    {
        $collateral = $document->collateral;
        $application = $collateral->application;

        $this->authorize('manageCollateral', $application);

        $this->loanAppService->removeCollateralDocument($document);

        return back()->with('success', 'Document removed.');
    }
}
