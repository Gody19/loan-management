<?php

namespace App\Http\Controllers;

use App\Models\ShareTransaction;
use Illuminate\Http\Request;

class ShareTransactionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ShareTransaction::class);

        $query = ShareTransaction::with(['account.member', 'account.product', 'creator']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('reference_number', 'like', "%{$request->search}%")
                    ->orWhereHas('account', function ($aq) use ($request) {
                        $aq->where('account_number', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
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
            'creator',
            'reversalOf',
            'reversedBy',
        ]);

        return view('share-transactions.show', ['transaction' => $shareTransaction]);
    }

    public function receipt(ShareTransaction $shareTransaction)
    {
        $this->authorize('view', $shareTransaction);

        $shareTransaction->load([
            'account.member',
            'account.product',
            'creator',
        ]);

        return view('share-transactions.receipt', ['transaction' => $shareTransaction]);
    }
}
