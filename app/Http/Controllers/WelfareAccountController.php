<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReverseTransactionRequest;
use App\Http\Requests\StoreWelfareBenefitRequest;
use App\Http\Requests\StoreWelfareContributionRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use App\Services\OrganizationContext;
use App\Services\WelfareTransactionService;
use Illuminate\Http\Request;

class WelfareAccountController extends Controller
{
    public function __construct(
        private WelfareTransactionService $transactionService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', WelfareAccount::class);

        $query = WelfareAccount::with(['member', 'fund']);

        OrganizationContext::scopeToUserOrganizations($query, $request->user());

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('account_number', 'like', "%{$request->search}%")
                    ->orWhereHas('member', function ($mq) use ($request) {
                        $mq->where('first_name', 'like', "%{$request->search}%")
                            ->orWhere('last_name', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->filled('fund_id')) {
            $query->where('welfare_fund_id', $request->fund_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $accounts = $query->latest()->paginate(15)->withQueryString();

        $organizations = OrganizationContext::scopedOrganizations($request->user())->active()->get();
        $funds = WelfareFund::active()->get();
        $members = Member::active()->get();

        return view('welfare-accounts.index', compact('accounts', 'funds', 'members', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', WelfareAccount::class);

        $members = Member::active()->get();
        $funds = WelfareFund::active()->get();
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::active()->get();

        return view('welfare-accounts.create', compact('members', 'funds', 'organizations', 'branches', 'groups'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', WelfareAccount::class);

        $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'welfare_fund_id' => ['required', 'exists:welfare_funds,id'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'vicoba_group_id' => ['required', 'exists:vicoba_groups,id'],
        ]);

        OrganizationContext::authorizeOrganization((int) $request->organization_id);

        $account = WelfareAccount::create([
            'member_id' => $request->member_id,
            'welfare_fund_id' => $request->welfare_fund_id,
            'organization_id' => $request->organization_id,
            'branch_id' => $request->branch_id,
            'vicoba_group_id' => $request->vicoba_group_id,
            'account_number' => WelfareAccount::generateAccountNumber(),
            'current_balance' => 0,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('welfare-accounts.show', $account)
            ->with('success', 'Welfare account "'.$account->account_number.'" created successfully.');
    }

    public function show(WelfareAccount $welfareAccount)
    {
        $this->authorize('view', $welfareAccount);

        $welfareAccount->load([
            'member',
            'fund',
            'transactions.creator',
        ]);

        return view('welfare-accounts.show', ['account' => $welfareAccount]);
    }

    public function contribute(WelfareAccount $welfareAccount)
    {
        $this->authorize('view', $welfareAccount);

        $welfareAccount->load('fund');

        return view('welfare-accounts.contribute', ['account' => $welfareAccount]);
    }

    public function doContribute(StoreWelfareContributionRequest $request, WelfareAccount $welfareAccount)
    {
        $this->authorize('update', $welfareAccount);

        try {
            $transaction = $this->transactionService->contribute(
                $welfareAccount,
                $request->validated()
            );

            return redirect()->route('welfare-transactions.show', $transaction)
                ->with('success', 'Welfare contribution recorded successfully. Transaction: '.$transaction->transaction_number);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('welfare-accounts.show', $welfareAccount)
                ->with('error', $e->getMessage());
        }
    }

    public function benefit(WelfareAccount $welfareAccount)
    {
        $this->authorize('view', $welfareAccount);

        $welfareAccount->load('fund');

        return view('welfare-accounts.benefit', ['account' => $welfareAccount]);
    }

    public function doBenefit(StoreWelfareBenefitRequest $request, WelfareAccount $welfareAccount)
    {
        $this->authorize('update', $welfareAccount);

        try {
            $transaction = $this->transactionService->benefit(
                $welfareAccount,
                $request->validated()
            );

            return redirect()->route('welfare-transactions.show', $transaction)
                ->with('success', 'Welfare benefit recorded successfully. Transaction: '.$transaction->transaction_number);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('welfare-accounts.show', $welfareAccount)
                ->with('error', $e->getMessage());
        }
    }

    public function reverse(ReverseTransactionRequest $request, \App\Models\WelfareTransaction $transaction)
    {
        $this->authorize('reverse', $transaction);

        try {
            $reversal = $this->transactionService->reverse(
                $transaction,
                $request->validated()['reason']
            );

            return redirect()->route('welfare-transactions.show', $reversal)
                ->with('success', 'Transaction reversed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('welfare-transactions.show', $transaction)
                ->with('error', $e->getMessage());
        }
    }
}
