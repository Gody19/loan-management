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
        $this->authorizeForMember($member, $kin);

        $this->memberService->updateNextOfKin($kin, $request->validated());

        return redirect()->route('members.show', $member)
            ->with('success', 'Next of kin updated successfully.');
    }

    public function destroy(Member $member, MemberNextOfKin $kin)
    {
        $this->authorizeForMember($member, $kin);

        $this->memberService->removeNextOfKin($kin);

        return redirect()->route('members.show', $member)
            ->with('success', 'Next of kin removed successfully.');
    }

    /**
     * The relative and the member are bound independently from the URL, so the
     * relative identifier would otherwise be trusted from user input. Without
     * this check, a user authorized for one member could read or overwrite the
     * next-of-kin identity records of any other member in the organization.
     */
    private function authorizeForMember(Member $member, MemberNextOfKin $kin): void
    {
        abort_unless($kin->member_id === $member->id, 403);

        $this->authorize('manageNextOfKin', $member);
    }
}
