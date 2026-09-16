<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuarantorRequest;
use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\Member;
use App\Services\LoanApplicationService;
use Illuminate\Http\Request;

class LoanApplicationGuarantorController extends Controller
{
    public function __construct(private LoanApplicationService $loanAppService) {}

    public function store(StoreGuarantorRequest $request, LoanApplication $loanApplication)
    {
        $this->authorize('manageGuarantors', $loanApplication);

        $guarantorMember = Member::findOrFail($request->guarantor_member_id);

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
}
