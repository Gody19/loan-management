<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberProfileRequest;
use App\Services\AuditService;
use App\Services\MemberDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberPortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $service = new MemberDashboardService($request->user());
        $data = $service->resolve();

        return view('member.dashboard', $data);
    }

    public function profile(Request $request): View
    {
        $member = $request->user()->member;

        return view('member.profile', ['member' => $member]);
    }

    public function editProfile(Request $request): View
    {
        $member = $request->user()->member;

        return view('member.profile-edit', ['member' => $member]);
    }

    public function updateProfile(UpdateMemberProfileRequest $request, AuditService $audit): RedirectResponse
    {
        $member = $request->user()->member;
        $old = $member->toArray();

        $member->update($request->validated());

        $audit->log(
            'member.profile_updated',
            $member,
            $old,
            $member->toArray()
        );

        return redirect()->route('member.profile')
            ->with('success', 'Your profile has been updated successfully.');
    }
}
