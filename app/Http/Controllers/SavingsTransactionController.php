<?php

namespace App\Http\Controllers;

use App\Models\SavingsTransaction;
use App\Services\OrganizationContext;
use App\Services\ReceiptService;
use Illuminate\Http\Request;

class SavingsTransactionController extends Controller
{
    public function __construct(private ReceiptService $receiptService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', SavingsTransaction::class);

        $query = SavingsTransaction::with(['account', 'member', 'paymentMethod', 'creator']);

        OrganizationContext::scopeToUserOrganizations($query, $request->user());

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_number', 'LIKE', "%{$request->search}%")
                    ->orWhereHas('member', fn ($mq) => $mq->where('member_number', 'LIKE', "%{$request->search}%"));
            });
        }
        if ($request->filled('transaction_type')) $query->where('transaction_type', $request->transaction_type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from_date')) $query->where('transaction_date', '>=', $request->from_date);
        if ($request->filled('to_date')) $query->where('transaction_date', '<=', $request->to_date);

        $transactions = $query->latest()->paginate(15)->withQueryString();
        return view('savings-transactions.index', compact('transactions'));
    }

    public function show(SavingsTransaction $savingsTransaction)
    {
        $this->authorize('view', $savingsTransaction);
        $savingsTransaction->load(['account', 'member', 'organization', 'branch', 'paymentMethod', 'creator']);
        return view('savings-transactions.show', ['transaction' => $savingsTransaction]);
    }

    public function receipt(SavingsTransaction $savingsTransaction)
    {
        $this->authorize('view', $savingsTransaction);
        $savingsTransaction->load(['account.member', 'account.product', 'account.organization', 'account.branch', 'account.vicobaGroup', 'paymentMethod', 'member', 'organization', 'branch', 'creator']);
        return view('receipts.savings', ['transaction' => $savingsTransaction]);
    }
}
