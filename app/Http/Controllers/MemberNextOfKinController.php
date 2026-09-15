<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNextOfKinRequest;
use App\Models\Member;
use App\Models\MemberNextOfKin;
use App\Services\MemberService;

class MemberNextOfKinController extends Controller
{
    public function __construct(
        private MemberService $memberService,
    ) {}

    public function store(StoreNextOfKinRequest $request, Member $member)
    {
        $this->authorize('manageNextOfKin', $member);

        $this->memberService->addNextOfKin($member, $request->validated());

        return redirect()->route('members.show', $member)
            ->with('success', 'Next of kin added successfully.');
    }

    public function update(StoreNextOfKinRequest $request, Member $member, MemberNextOfKin $kin)
    {
        $this->authorize('manageNextOfKin', $member);

        $this->memberService->updateNextOfKin($kin, $request->validated());

        return redirect()->route('members.show', $member)
            ->with('success', 'Next of kin updated successfully.');
    }

    public function destroy(Member $member, MemberNextOfKin $kin)
    {
        $this->authorize('manageNextOfKin', $member);

        $this->memberService->removeNextOfKin($kin);

        return redirect()->route('members.show', $member)
            ->with('success', 'Next of kin removed successfully.');
    }
}
