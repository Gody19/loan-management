<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollateralRequest;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Services\LoanApplicationService;

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
}
