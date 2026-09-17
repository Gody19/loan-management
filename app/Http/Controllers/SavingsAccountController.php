<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavingsAccountRequest;
use App\Http\Requests\StoreSavingsDepositRequest;
use App\Http\Requests\StoreSavingsWithdrawalRequest;
use App\Http\Requests\ReverseTransactionRequest;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Branch;
use App\Models\VicobaGroup;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Services\OrganizationContext;
use App\Services\SavingsAccountNumberGenerator;
use App\Services\SavingsTransactionService;
use Illuminate\Http\Request;

class SavingsAccountController extends Controller
{
    public function __construct(
        private SavingsAccountNumberGenerator $numberGenerator,
        private SavingsTransactionService $transactionService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', SavingsAccount::class);

        $query = SavingsAccount::with(['member', 'product', 'organization']);

        OrganizationContext::scopeToUserOrganizations($query, $request->user());

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('account_number', 'LIKE', "%{$request->search}%")
                    ->orWhereHas('member', fn ($mq) => $mq->where('member_number', 'LIKE', "%{$request->search}%")
                        ->orWhere('first_name', 'LIKE', "%{$request->search}%")
                        ->orWhere('last_name', 'LIKE', "%{$request->search}%"));
            });
        }
        if ($request->filled('organization_id')) $query->where('organization_id', $request->organization_id);
        if ($request->filled('status')) $query->where('status', $request->status);

        $accounts = $query->latest()->paginate(15)->withQueryString();

        $organizations = OrganizationContext::scopedOrganizations($request->user())->active()->get();

        return view('savings-accounts.index', compact('accounts', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', SavingsAccount::class);
        $members = Member::active()->get();
        $products = SavingsProduct::where('status', 'active')->get();
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::where('status', 'active')->get();

        return view('savings-accounts.create', compact('members', 'products', 'organizations', 'branches', 'groups'));
    }

    public function store(StoreSavingsAccountRequest $request)
    {
        $data = $request->validated();
        $data['account_number'] = $this->numberGenerator->generate();
        $data['current_balance'] = 0;

        $account = SavingsAccount::create($data);
        return redirect()->route('savings-accounts.show', $account)
            ->with('success', 'Savings account ' . $account->account_number . ' created.');
    }

    public function show(SavingsAccount $savingsAccount)
    {
        $this->authorize('view', $savingsAccount);
        $savingsAccount->load(['member', 'product', 'organization', 'branch', 'vicobaGroup']);
        $transactions = $savingsAccount->transactions()
            ->with(['paymentMethod', 'creator'])
            ->latest()
            ->paginate(15);

        $totalDeposits = $savingsAccount->transactions()
            ->where('transaction_type', 'deposit')
            ->sum('amount');
        $totalWithdrawals = $savingsAccount->transactions()
            ->where('transaction_type', 'withdrawal')
            ->sum('amount');

        return view('savings-accounts.show', array_merge(
            ['account' => $savingsAccount, 'transactions' => $transactions],
            compact('totalDeposits', 'totalWithdrawals')
        ));
    }

    public function deposit(SavingsAccount $savingsAccount)
    {
        $this->authorize('deposit', $savingsAccount);
        $paymentMethods = PaymentMethod::where('status', 'active')->get();
        return view('savings-accounts.deposit', ['account' => $savingsAccount, 'paymentMethods' => $paymentMethods]);
    }

    public function doDeposit(StoreSavingsDepositRequest $request, SavingsAccount $savingsAccount)
    {
        $this->authorize('deposit', $savingsAccount);
        try {
            $this->transactionService->deposit($savingsAccount, $request->validated());
            return redirect()->route('savings-accounts.show', $savingsAccount)
                ->with('success', 'Deposit completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('savings-accounts.show', $savingsAccount)
                ->with('error', $e->getMessage());
        }
    }

    public function withdraw(SavingsAccount $savingsAccount)
    {
        $this->authorize('withdraw', $savingsAccount);
        $paymentMethods = PaymentMethod::where('status', 'active')->get();
        return view('savings-accounts.withdraw', ['account' => $savingsAccount, 'paymentMethods' => $paymentMethods]);
    }

    public function doWithdraw(StoreSavingsWithdrawalRequest $request, SavingsAccount $savingsAccount)
    {
        $this->authorize('withdraw', $savingsAccount);
        try {
            $this->transactionService->withdraw($savingsAccount, $request->validated());
            return redirect()->route('savings-accounts.show', $savingsAccount)
                ->with('success', 'Withdrawal completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('savings-accounts.show', $savingsAccount)
                ->with('error', $e->getMessage());
        }
    }

    public function reverse(ReverseTransactionRequest $request, \App\Models\SavingsTransaction $transaction)
    {
        $this->authorize('reverse', $transaction);
        try {
            $reversal = $this->transactionService->reverse($transaction, $request->validated()['reason']);
            return redirect()->route('savings-accounts.show', $transaction->account)
                ->with('success', 'Transaction reversed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('savings-accounts.show', $transaction->account)
                ->with('error', $e->getMessage());
        }
    }
}
