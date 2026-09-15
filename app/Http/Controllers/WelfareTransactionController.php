<?php

namespace App\Http\Controllers;

use App\Models\WelfareTransaction;
use Illuminate\Http\Request;

class WelfareTransactionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', WelfareTransaction::class);

        $query = WelfareTransaction::with(['account.member', 'account.fund', 'creator']);

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
            $query->where('welfare_account_id', $request->account_id);
        }

        if ($request->filled('from_date')) {
            $query->where('transaction_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        $transactions = $query->latest('transaction_date')->paginate(15)->withQueryString();

        return view('welfare-transactions.index', compact('transactions'));
    }

    public function show(WelfareTransaction $welfareTransaction)
    {
        $this->authorize('view', $welfareTransaction);

        $welfareTransaction->load([
            'account.member',
            'account.fund',
            'creator',
            'reversalOf',
            'reversedBy',
        ]);

        return view('welfare-transactions.show', ['transaction' => $welfareTransaction]);
    }

    public function receipt(WelfareTransaction $welfareTransaction)
    {
        $this->authorize('view', $welfareTransaction);

        $welfareTransaction->load([
            'account.member',
            'account.fund',
            'creator',
        ]);

        return view('welfare-transactions.receipt', ['transaction' => $welfareTransaction]);
    }
}
