<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberSavingsDepositRequest;
use App\Http\Requests\MemberSavingsWithdrawRequest;
use App\Http\Requests\MemberSharePurchaseRequest;
use App\Http\Requests\MemberShareRedemptionRequest;
use App\Http\Requests\MemberWelfareBenefitRequest;
use App\Http\Requests\MemberWelfareContributionRequest;
use App\Http\Requests\UpdateMemberProfileRequest;
use App\Models\PaymentMethod;
use App\Services\AuditService;
use App\Services\MemberDashboardService;
use App\Services\SavingsTransactionService;
use App\Services\ShareTransactionService;
use App\Services\WelfareBenefitRequestService;
use App\Services\WelfareTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberPortalController extends Controller
{
    public function __construct(
        protected SavingsTransactionService $savingsService,
        protected ShareTransactionService $shareService,
        protected WelfareTransactionService $welfareService,
        protected WelfareBenefitRequestService $benefitRequestService,
    ) {}

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

    // ==================== Savings ====================

    public function savings(Request $request): View
    {
        $member = $request->user()->member;
        $accounts = $member->savingsAccounts()->with('product')->get();

        $recentTransactions = $accounts->flatMap(function ($account) {
            return $account->transactions()
                ->orderBy('transaction_date', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($tx) => [
                    'transaction_number' => $tx->transaction_number,
                    'type' => $tx->transaction_type->label(),
                    'amount' => $tx->amount,
                    'date' => $tx->transaction_date,
                    'status' => $tx->status->label(),
                    'account_number' => $account->account_number,
                ]);
        })->sortByDesc('date')->take(10)->values();

        $paymentMethods = PaymentMethod::where('status', 'active')
            ->where('organization_id', $member->organization_id)
            ->get();

        return view('member.savings', compact('member', 'accounts', 'recentTransactions', 'paymentMethods'));
    }

    public function depositSavings(MemberSavingsDepositRequest $request): RedirectResponse
    {
        $member = $request->user()->member;

        $savingsAccount = $member->savingsAccounts()
            ->whereKey($request->validated('savings_account_id'))
            ->firstOrFail();

        $data = collect($request->validated())
            ->except('savings_account_id')
            ->toArray();

        try {
            $this->savingsService->deposit($savingsAccount, $data);

            return redirect()->route('member.savings')
                ->with('success', 'Savings deposit completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('member.savings')
                ->with('error', $e->getMessage());
        }
    }

    public function withdrawSavings(MemberSavingsWithdrawRequest $request): RedirectResponse
    {
        $member = $request->user()->member;

        $savingsAccount = $member->savingsAccounts()
            ->whereKey($request->validated('savings_account_id'))
            ->firstOrFail();

        $data = collect($request->validated())
            ->except('savings_account_id')
            ->toArray();

        try {
            $this->savingsService->withdraw($savingsAccount, $data);

            return redirect()->route('member.savings')
                ->with('success', 'Savings withdrawal completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('member.savings')
                ->with('error', $e->getMessage());
        }
    }

    // ==================== Shares ====================

    public function shares(Request $request): View
    {
        $member = $request->user()->member;
        $accounts = $member->shareAccounts()->with('product')->get();

        $recentTransactions = $accounts->flatMap(function ($account) {
            return $account->transactions()
                ->orderBy('transaction_date', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($tx) => [
                    'transaction_number' => $tx->transaction_number,
                    'type' => $tx->transaction_type->label(),
                    'quantity' => $tx->quantity,
                    'amount' => $tx->amount,
                    'date' => $tx->transaction_date,
                    'status' => $tx->status->label(),
                    'account_number' => $account->account_number,
                ]);
        })->sortByDesc('date')->take(10)->values();

        $paymentMethods = PaymentMethod::where('status', 'active')
            ->where('organization_id', $member->organization_id)
            ->get();

        return view('member.shares', compact('member', 'accounts', 'recentTransactions', 'paymentMethods'));
    }

    public function purchaseShares(MemberSharePurchaseRequest $request): RedirectResponse
    {
        $member = $request->user()->member;

        $shareAccount = $member->shareAccounts()
            ->whereKey($request->validated('share_account_id'))
            ->firstOrFail();

        $shareAccount->load('product');

        $quantity = (int) $request->validated('quantity');
        $sharePrice = (float) ($shareAccount->product->share_price ?? 0);
        $amount = $quantity * $sharePrice;

        $data = [
            'quantity' => $quantity,
            'share_price' => $sharePrice,
            'amount' => $amount,
            'payment_method_id' => $request->validated('payment_method_id'),
            'transaction_date' => $request->validated('transaction_date'),
            'reference' => $request->validated('reference') ?? null,
            'description' => $request->validated('description') ?? null,
        ];

        try {
            $this->shareService->purchase($shareAccount, $data);

            return redirect()->route('member.shares')
                ->with('success', 'Share purchase completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('member.shares')
                ->with('error', $e->getMessage());
        }
    }

    public function redeemShares(MemberShareRedemptionRequest $request): RedirectResponse
    {
        $member = $request->user()->member;

        $shareAccount = $member->shareAccounts()
            ->whereKey($request->validated('share_account_id'))
            ->firstOrFail();

        $data = [
            'quantity' => (int) $request->validated('quantity'),
            'payment_method_id' => $request->validated('payment_method_id'),
            'transaction_date' => $request->validated('transaction_date'),
            'reference' => $request->validated('reference') ?? null,
            'description' => $request->validated('description') ?? null,
        ];

        try {
            $this->shareService->redeem($shareAccount, $data);

            return redirect()->route('member.shares')
                ->with('success', 'Share redemption completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('member.shares')
                ->with('error', $e->getMessage());
        }
    }

    // ==================== Welfare ====================

    public function welfare(Request $request): View
    {
        $member = $request->user()->member;
        $accounts = $member->welfareAccounts()->with('fund')->get();

        $recentTransactions = $accounts->flatMap(function ($account) {
            return $account->transactions()
                ->orderBy('transaction_date', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($tx) => [
                    'transaction_number' => $tx->transaction_number,
                    'type' => $tx->transaction_type->label(),
                    'amount' => $tx->amount,
                    'date' => $tx->transaction_date,
                    'status' => $tx->status->label(),
                    'account_number' => $account->account_number,
                ]);
        })->sortByDesc('date')->take(10)->values();

        $benefitRequests = $member->welfareBenefitRequests()
            ->with('account', 'paymentMethod')
            ->latest()
            ->limit(10)
            ->get();

        $paymentMethods = PaymentMethod::where('status', 'active')
            ->where('organization_id', $member->organization_id)
            ->get();

        return view('member.welfare', compact('member', 'accounts', 'recentTransactions', 'benefitRequests', 'paymentMethods'));
    }

    public function contributeWelfare(MemberWelfareContributionRequest $request): RedirectResponse
    {
        $member = $request->user()->member;

        $welfareAccount = $member->welfareAccounts()
            ->whereKey($request->validated('welfare_account_id'))
            ->firstOrFail();

        $data = collect($request->validated())
            ->except('welfare_account_id')
            ->toArray();

        try {
            $this->welfareService->contribute($welfareAccount, $data);

            return redirect()->route('member.welfare')
                ->with('success', 'Welfare contribution completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('member.welfare')
                ->with('error', $e->getMessage());
        }
    }

    public function requestBenefit(MemberWelfareBenefitRequest $request): RedirectResponse
    {
        $member = $request->user()->member;

        $welfareAccount = $member->welfareAccounts()
            ->whereKey($request->validated('welfare_account_id'))
            ->firstOrFail();

        $data = collect($request->validated())
            ->except('welfare_account_id')
            ->toArray();

        try {
            $this->benefitRequestService->createRequest($welfareAccount, $data);

            return redirect()->route('member.welfare')
                ->with('success', 'Welfare benefit request submitted successfully. It will be reviewed by an administrator.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('member.welfare')
                ->with('error', $e->getMessage());
        }
    }

    // ==================== Loans ====================

    public function loans(Request $request): View
    {
        $member = $request->user()->member;

        $loans = $member->loans()
            ->with('loanPlan')
            ->get();

        $activeLoans = $loans->filter(fn ($loan) => in_array($loan->status->value, ['active', 'disbursed']));

        $loanDetails = $activeLoans->map(function ($loan) {
            $nextInstallment = $loan->repaymentSchedule()
                ->where('status', 'pending')
                ->where('due_date', '>=', now()->toDateString())
                ->orderBy('due_date')
                ->first();

            $overdueInstallment = $loan->repaymentSchedule()
                ->where('status', 'overdue')
                ->orderBy('due_date')
                ->first();

            $daysPastDue = 0;
            if ($overdueInstallment) {
                $daysPastDue = now()->diffInDays($overdueInstallment->due_date);
            }

            return [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'plan_name' => $loan->loanPlan->name ?? '-',
                'principal_amount' => $loan->principal_amount,
                'outstanding_balance' => $loan->outstanding_balance,
                'amount_paid' => $loan->amount_paid,
                'status' => $loan->status,
                'installments_paid' => $loan->installments_paid,
                'total_installments' => $loan->total_installments,
                'next_installment' => $nextInstallment,
                'days_past_due' => $daysPastDue,
                'disbursement_date' => $loan->disbursement_date,
                'maturity_date' => $loan->maturity_date,
            ];
        });

        $completedLoans = $loans->filter(fn ($loan) => $loan->status->value === 'completed');

        $recentRepayments = $member->repayments()
            ->with('loan')
            ->orderBy('payment_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($repayment) => [
                'repayment_number' => $repayment->repayment_number,
                'loan_number' => $repayment->loan->loan_number ?? '-',
                'amount' => $repayment->amount,
                'date' => $repayment->payment_date,
                'status' => $repayment->status->label(),
            ]);

        return view('member.loans', compact('member', 'loanDetails', 'completedLoans', 'recentRepayments'));
    }

    // ==================== Transactions ====================

    public function transactions(Request $request): View
    {
        $member = $request->user()->member;
        $memberId = $member->id;
        $orgId = $member->organization_id;

        $savings = \Illuminate\Support\Facades\DB::table('savings_transactions')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'transaction_number as reference', 'transaction_type as type', 'amount', 'transaction_date as date', 'status', \Illuminate\Support\Facades\DB::raw("'savings' as category"))
            ->get();

        $shares = \Illuminate\Support\Facades\DB::table('share_transactions')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'transaction_number as reference', 'transaction_type as type', 'amount', 'transaction_date as date', 'status', \Illuminate\Support\Facades\DB::raw("'shares' as category"))
            ->get();

        $welfare = \Illuminate\Support\Facades\DB::table('welfare_transactions')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'transaction_number as reference', 'transaction_type as type', 'amount', 'transaction_date as date', 'status', \Illuminate\Support\Facades\DB::raw("'welfare' as category"))
            ->get();

        $repayments = \Illuminate\Support\Facades\DB::table('loan_repayments')
            ->where('member_id', $memberId)
            ->where('organization_id', $orgId)
            ->select('id', 'repayment_number as reference', \Illuminate\Support\Facades\DB::raw("'repayment' as type"), 'amount', 'payment_date as date', 'status', \Illuminate\Support\Facades\DB::raw("'loan_repayment' as category"))
            ->get();

        $allTransactions = $savings->concat($shares)->concat($welfare)->concat($repayments)
            ->sortByDesc('date')
            ->values();

        $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $allTransactions->forPage(1, 20),
            $allTransactions->count(),
            20,
            1,
            ['path' => route('member.transactions')]
        );

        return view('member.transactions', compact('member', 'transactions'));
    }

    // ==================== Notifications ====================

    public function notifications(Request $request): View
    {
        $member = $request->user()->member;

        return view('member.notifications', compact('member'));
    }
}
