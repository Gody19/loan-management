<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReverseTransactionRequest;
use App\Http\Requests\StoreShareAccountRequest;
use App\Http\Requests\StoreSharePurchaseRequest;
use App\Http\Requests\StoreShareRedemptionRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\ShareAccount;
use App\Models\VicobaGroup;
use App\Models\ShareProduct;
use App\Services\ShareTransactionService;
use Illuminate\Http\Request;

class ShareAccountController extends Controller
{
    public function __construct(
        private ShareTransactionService $transactionService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ShareAccount::class);

        $query = ShareAccount::with(['member', 'product']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('account_number', 'like', "%{$request->search}%")
                    ->orWhereHas('member', function ($mq) use ($request) {
                        $mq->where('first_name', 'like', "%{$request->search}%")
                            ->orWhere('last_name', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->filled('product_id')) {
            $query->where('share_product_id', $request->product_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $accounts = $query->latest()->paginate(15)->withQueryString();

        $organizations = Organization::active()->get();
        $products = ShareProduct::active()->get();
        $members = Member::active()->get();

        return view('share-accounts.index', compact('accounts', 'products', 'members', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', ShareAccount::class);

        $members = Member::active()->get();
        $products = ShareProduct::active()->get();
        $organizations = Organization::active()->get();
        $branches = Branch::active()->get();
        $groups = VicobaGroup::active()->get();

        return view('share-accounts.create', compact('members', 'products', 'organizations', 'branches', 'groups'));
    }

    public function store(StoreShareAccountRequest $request)
    {
        $this->authorize('create', ShareAccount::class);

        $account = ShareAccount::create([
            'member_id' => $request->member_id,
            'share_product_id' => $request->share_product_id,
            'organization_id' => $request->organization_id,
            'branch_id' => $request->branch_id,
            'vicoba_group_id' => $request->vicoba_group_id,
            'account_number' => ShareAccount::generateAccountNumber(),
            'total_shares' => 0,
            'total_value' => 0,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('share-accounts.show', $account)
            ->with('success', 'Share account "'.$account->account_number.'" created successfully.');
    }

    public function show(ShareAccount $shareAccount)
    {
        $this->authorize('view', $shareAccount);

        $shareAccount->load([
            'member',
            'product',
            'transactions.creator',
        ]);

        return view('share-accounts.show', ['account' => $shareAccount]);
    }

    public function purchase(ShareAccount $shareAccount)
    {
        $this->authorize('view', $shareAccount);

        $shareAccount->load('product');

        return view('share-accounts.purchase', ['account' => $shareAccount]);
    }

    public function doPurchase(StoreSharePurchaseRequest $request, ShareAccount $shareAccount)
    {
        $this->authorize('update', $shareAccount);

        try {
            $transaction = $this->transactionService->purchase(
                $shareAccount,
                $request->validated()
            );

            return redirect()->route('share-transactions.show', $transaction)
                ->with('success', 'Share purchase recorded successfully. Transaction: '.$transaction->transaction_number);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('share-accounts.show', $shareAccount)
                ->with('error', $e->getMessage());
        }
    }

    public function redeem(ShareAccount $shareAccount)
    {
        $this->authorize('view', $shareAccount);

        $shareAccount->load('product');

        return view('share-accounts.redeem', ['account' => $shareAccount]);
    }

    public function doRedeem(StoreShareRedemptionRequest $request, ShareAccount $shareAccount)
    {
        $this->authorize('update', $shareAccount);

        try {
            $transaction = $this->transactionService->redeem(
                $shareAccount,
                $request->validated()
            );

            return redirect()->route('share-transactions.show', $transaction)
                ->with('success', 'Share redemption recorded successfully. Transaction: '.$transaction->transaction_number);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('share-accounts.show', $shareAccount)
                ->with('error', $e->getMessage());
        }
    }

    public function reverse(ReverseTransactionRequest $request, \App\Models\ShareTransaction $transaction)
    {
        $this->authorize('reverse', $transaction);

        try {
            $reversal = $this->transactionService->reverse(
                $transaction,
                $request->validated()['reason']
            );

            return redirect()->route('share-transactions.show', $reversal)
                ->with('success', 'Transaction reversed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('share-transactions.show', $transaction)
                ->with('error', $e->getMessage());
        }
    }
}
