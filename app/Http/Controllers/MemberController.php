<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\VicobaGroup;
use App\Services\MemberService;
use App\Services\FinancialStatementService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(
        private MemberService $memberService,
        private FinancialStatementService $statementService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Member::class);

        $query = Member::with(['organization', 'branch', 'vicobaGroup']);
        $query = $this->memberService->getForUser($query, auth()->user());

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('organization_id')) {
            $query->byOrganization($request->organization_id);
        }

        if ($request->filled('branch_id')) {
            $query->byBranch($request->branch_id);
        }

        if ($request->filled('vicoba_group_id')) {
            $query->byGroup($request->vicoba_group_id);
        }

        if ($request->filled('membership_status')) {
            $query->byStatus($request->membership_status);
        }

        if ($request->filled('gender')) {
            $query->byGender($request->gender);
        }

        if ($request->filled('from_date')) {
            $query->where('joining_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('joining_date', '<=', $request->to_date);
        }

        $members = $query->latest()->paginate(15)->withQueryString();

        $organizations = Organization::active()->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::active()->get();

        return view('members.index', compact('members', 'organizations', 'branches', 'groups'));
    }

    public function create()
    {
        $this->authorize('create', Member::class);

        $organizations = Organization::active()->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::active()->get();

        return view('members.create', compact('organizations', 'branches', 'groups'));
    }

    public function store(StoreMemberRequest $request)
    {
        $this->authorize('create', Member::class);

        $result = $this->memberService->create($request->validated());
        $member = $result['member'];
        $tempPassword = $result['temp_password'];

        return redirect()->route('members.show', $member)
            ->with('success', 'Member "'.$member->full_name.'" created successfully. Member No: '.$member->member_number.'. Portal credentials: '.$request->email.' / password');
    }

    public function show(Member $member)
    {
        $this->authorize('view', $member);

        $member->load([
            'organization',
            'branch',
            'vicobaGroup',
            'nextOfKins',
            'documents.verifier',
            'statusHistories.changer',
            'creator',
            'savingsAccounts.product',
            'shareAccounts.product',
            'welfareAccounts.fund',
        ]);

        return view('members.show', compact('member'));
    }

    public function edit(Member $member)
    {
        $this->authorize('update', $member);

        $member->load(['organization', 'branch', 'vicobaGroup']);

        $organizations = Organization::active()->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::active()->get();

        return view('members.edit', compact('member', 'organizations', 'branches', 'groups'));
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $this->authorize('update', $member);

        $this->memberService->update($member, $request->validated());

        return redirect()->route('members.show', $member)
            ->with('success', 'Member updated successfully.');
    }

    public function destroy(Member $member)
    {
        $this->authorize('delete', $member);

        if ($member->membership_status->value === 'active') {
            $this->memberService->archiveMember($member, 'Member record archived by '.auth()->user()->fullname);
        }

        $member->delete();

        return redirect()->route('members.index')
            ->with('success', 'Member archived successfully.');
    }

    public function changeStatus(Request $request, Member $member)
    {
        $this->authorize('update', $member);

        $request->validate([
            'status' => ['required', 'in:active,suspended,inactive,exited'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->memberService->changeStatus(
                $member,
                MemberStatus::from($request->status),
                $request->reason
            );

            return redirect()->route('members.show', $member)
                ->with('success', 'Member status changed to '.MemberStatus::from($request->status)->label().'.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('members.show', $member)
                ->with('error', $e->getMessage());
        }
    }

    public function getBranches(Request $request)
    {
        $branches = Branch::where('organization_id', $request->organization_id)
            ->active()
            ->get(['id', 'name']);

        return response()->json($branches);
    }

    public function getGroups(Request $request)
    {
        $groups = VicobaGroup::where('branch_id', $request->branch_id)
            ->active()
            ->get(['id', 'name']);

        return response()->json($groups);
    }

    public function statement(Request $request, Member $member)
    {
        $this->authorize('view', $member);

        $type = $request->get('type', 'savings');
        $from = $request->get('from');
        $to = $request->get('to');

        $data = match ($type) {
            'shares' => [
                'accounts' => $member->shareAccounts()->with('product')->get(),
                'transactions' => null,
            ],
            'welfare' => [
                'accounts' => $member->welfareAccounts()->with('fund')->get(),
                'transactions' => null,
            ],
            default => [
                'accounts' => $member->savingsAccounts()->with('product')->get(),
                'transactions' => null,
            ],
        };

        $accountId = $request->get('account_id');
        if ($accountId) {
            $data['transactions'] = match ($type) {
                'shares' => $this->statementService->getShareStatement(
                    $member->shareAccounts()->findOrFail($accountId), $from, $to
                ),
                'welfare' => $this->statementService->getWelfareStatement(
                    $member->welfareAccounts()->findOrFail($accountId), $from, $to
                ),
                default => $this->statementService->getSavingsStatement(
                    $member->savingsAccounts()->findOrFail($accountId), $from, $to
                ),
            };
        }

        $data['summary'] = $this->statementService->getMemberFinancialSummary($member);
        $data['type'] = $type;

        return view('members.statement', array_merge(['member' => $member], $data));
    }
}
