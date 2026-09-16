<?php

namespace App\Http\Controllers;

use App\Models\ShareTransaction;
use App\Services\ReceiptService;
use Illuminate\Http\Request;

class ShareTransactionController extends Controller
{
    public function __construct(private ReceiptService $receiptService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ShareTransaction::class);

        $query = ShareTransaction::with(['account.member', 'account.product', 'creator']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_number', 'like', "%{$request->search}%")
                    ->orWhereHas('account', function ($aq) use ($request) {
                        $aq->where('account_number', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('account_id')) {
            $query->where('share_account_id', $request->account_id);
        }

        if ($request->filled('from_date')) {
            $query->where('transaction_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        $transactions = $query->latest('transaction_date')->paginate(15)->withQueryString();

        return view('share-transactions.index', compact('transactions'));
    }

    public function show(ShareTransaction $shareTransaction)
    {
        $this->authorize('view', $shareTransaction);

        $shareTransaction->load([
            'account.member',
            'account.product',
            'account.organization',
            'account.branch',
            'account.vicobaGroup',
            'member',
            'organization',
            'branch',
            'creator',
            'reversalOf',
            'reversedBy',
            'paymentMethod',
        ]);

        return view('share-transactions.show', ['transaction' => $shareTransaction]);
    }

    public function receipt(ShareTransaction $shareTransaction)
    {
        $this->authorize('view', $shareTransaction);

        $shareTransaction->load([
            'account.member',
            'account.product',
            'account.organization',
            'account.branch',
            'account.vicobaGroup',
            'member',
            'organization',
            'branch',
            'creator',
            'paymentMethod',
        ]);

        return view('share-transactions.receipt', ['transaction' => $shareTransaction]);
    }
}
