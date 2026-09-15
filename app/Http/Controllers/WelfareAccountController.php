<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReverseTransactionRequest;
use App\Http\Requests\StoreWelfareBenefitRequest;
use App\Http\Requests\StoreWelfareContributionRequest;
use App\Models\Member;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
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

        $accounts = $query->latest()->paginate(15)->withQueryString();

        $funds = WelfareFund::active()->get();
        $members = Member::active()->get();

        return view('welfare-accounts.index', compact('accounts', 'funds', 'members'));
    }

    public function create()
    {
        $this->authorize('create', WelfareAccount::class);

        $members = Member::active()->get();
        $funds = WelfareFund::active()->get();

        return view('welfare-accounts.create', compact('members', 'funds'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', WelfareAccount::class);

        $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'welfare_fund_id' => ['required', 'exists:welfare_funds,id'],
        ]);

        $account = WelfareAccount::create([
            'member_id' => $request->member_id,
            'welfare_fund_id' => $request->welfare_fund_id,
            'account_number' => WelfareAccount::generateAccountNumber(),
            'status' => 'active',
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
        $this->authorize('view', $welfareAccount);

        $transaction = $this->transactionService->contribute(
            $welfareAccount,
            $request->validated()
        );

        return redirect()->route('welfare-transactions.show', $transaction)
            ->with('success', 'Welfare contribution recorded successfully. Transaction: '.$transaction->reference_number);
    }

    public function benefit(WelfareAccount $welfareAccount)
    {
        $this->authorize('view', $welfareAccount);

        $welfareAccount->load('fund');

        return view('welfare-accounts.benefit', ['account' => $welfareAccount]);
    }

    public function doBenefit(StoreWelfareBenefitRequest $request, WelfareAccount $welfareAccount)
    {
        $this->authorize('view', $welfareAccount);

        $transaction = $this->transactionService->benefit(
            $welfareAccount,
            $request->validated()
        );

        return redirect()->route('welfare-transactions.show', $transaction)
            ->with('success', 'Welfare benefit recorded successfully. Transaction: '.$transaction->reference_number);
    }

    public function reverse(ReverseTransactionRequest $request, WelfareAccount $welfareAccount)
    {
        $this->authorize('view', $welfareAccount);

        $transaction = $this->transactionService->reverse(
            $welfareAccount,
            $request->validated()
        );

        return redirect()->route('welfare-transactions.show', $transaction)
            ->with('success', 'Transaction reversed successfully.');
    }
}
