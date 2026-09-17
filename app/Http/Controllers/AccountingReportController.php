<?php

namespace App\Http\Controllers;

use App\Services\GeneralLedgerService;
use App\Services\TrialBalanceService;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use App\Services\CashBankLedgerService;
use App\Services\ChartOfAccountsService;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function __construct(
        protected GeneralLedgerService $ledgerService,
        protected TrialBalanceService $trialBalanceService,
        protected BalanceSheetService $balanceSheetService,
        protected IncomeStatementService $incomeStatementService,
        protected CashBankLedgerService $cashLedgerService,
        protected ChartOfAccountsService $chartService,
    ) {}

    public function generalLedger(Request $request)
    {
        $organization = $this->resolveOrganization();

        $data = $this->ledgerService->getLedger(
            $organization->id,
            $request->account_id,
            $request->branch_id,
            $request->start_date,
            $request->end_date,
            $request->period_id,
        );

        $accounts = $this->chartService->getAccounts($organization->id);

        return view('accounting.reports.general-ledger', compact('organization', 'data', 'accounts'));
    }

    public function trialBalance(Request $request)
    {
        $organization = $this->resolveOrganization();

        $data = $this->trialBalanceService->generate(
            $organization->id,
            $request->start_date,
            $request->end_date,
            $request->period_id,
        );

        return view('accounting.reports.trial-balance', compact('organization', 'data'));
    }

    public function balanceSheet(Request $request)
    {
        $organization = $this->resolveOrganization();

        $data = $this->balanceSheetService->generate(
            $organization->id,
            $request->as_of_date,
        );

        return view('accounting.reports.balance-sheet', compact('organization', 'data'));
    }

    public function incomeStatement(Request $request)
    {
        $organization = $this->resolveOrganization();

        $data = $this->incomeStatementService->generate(
            $organization->id,
            $request->start_date,
            $request->end_date,
            $request->period_id,
        );

        return view('accounting.reports.income-statement', compact('organization', 'data'));
    }

    public function cashLedger(Request $request)
    {
        $organization = $this->resolveOrganization();

        $assetAccounts = ChartOfAccount::where('organization_id', $organization->id)
            ->where('account_type', 'asset')
            ->whereIn('account_code', ['1100', '1200', '1300'])
            ->orderBy('account_code')
            ->get();

        $data = null;
        if ($request->filled('account_id')) {
            $data = $this->cashLedgerService->generate(
                $organization->id,
                $request->account_id,
                $request->start_date,
                $request->end_date,
            );
        }

        return view('accounting.reports.cash-ledger', compact('organization', 'assetAccounts', 'data'));
    }

    protected function resolveOrganization()
    {
        $orgId = session('organization_id') ?? auth()->user()->organizations()->first()?->id;

        if (!$orgId) {
            abort(403, 'No organization selected.');
        }

        return \App\Models\Organization::findOrFail($orgId);
    }
}
