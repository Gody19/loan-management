<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\LoanDelinquencyService;
use App\Services\LoanRepaymentScheduleService;
use Illuminate\Http\Request;

class LoanCollectionController extends Controller
{
    public function __construct(
        private LoanDelinquencyService $delinquencyService,
        private LoanRepaymentScheduleService $scheduleService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Loan::class);

        $user = $request->user();

        if ($user->hasRole('Super Administrator')) {
            $orgIds = $user->organizations()->pluck('organizations.id')->toArray();
            if (!empty($orgIds)) {
                $delinquentLoans = Loan::whereIn('organization_id', $orgIds)
                    ->where('status', LoanStatus::Active)
                    ->whereHas('repaymentSchedule', function ($q) {
                        $q->whereIn('status', ['pending', 'partial', 'overdue'])
                          ->where('outstanding_amount', '>', 0)
                          ->where('due_date', '<', now()->toDateString());
                    })
                    ->with(['member', 'branch', 'loanPlan'])
                    ->get();
            } else {
                $delinquentLoans = collect();
            }
        } else {
            $orgIds = $user->organizations()->pluck('organizations.id')->toArray();
            if (!empty($orgIds)) {
                $delinquentLoans = Loan::whereIn('organization_id', $orgIds)
                    ->where('status', LoanStatus::Active)
                    ->whereHas('repaymentSchedule', function ($q) {
                        $q->whereIn('status', ['pending', 'partial', 'overdue'])
                          ->where('outstanding_amount', '>', 0)
                          ->where('due_date', '<', now()->toDateString());
                    })
                    ->with(['member', 'branch', 'loanPlan'])
                    ->get();
            } else {
                $delinquentLoans = collect();
            }
        }

        $collectionStats = [
            'par30' => $this->getPARForOrg($user, 30),
            'par60' => $this->getPARForOrg($user, 60),
            'par90' => $this->getPARForOrg($user, 90),
        ];

        return view('loan-collections.index', compact('delinquentLoans', 'collectionStats'));
    }

    public function statement(Loan $loan)
    {
        $this->authorize('view', $loan);

        $loan->load([
            'member', 'loanPlan', 'branch', 'organization',
            'repaymentSchedule',
            'repayments' => function ($q) {
                $q->where('status', 'posted')->orderBy('payment_date');
            },
            'repayments.allocations.installment',
        ]);

        $scheduleSummary = $this->scheduleService->getScheduleSummary($loan);

        return view('loan-statements.show', compact('loan', 'scheduleSummary'));
    }

    private function getPARForOrg($user, int $threshold): array
    {
        if ($user->hasRole('Super Administrator')) {
            $orgIds = $user->organizations()->pluck('organizations.id')->toArray();
        } else {
            $orgIds = $user->organizations()->pluck('organizations.id')->toArray();
        }

        if (empty($orgIds)) {
            return ['total_outstanding' => 0, 'delinquent_outstanding' => 0, 'par_percentage' => 0];
        }

        $totalOutstanding = 0;
        $delinquentOutstanding = 0;

        foreach ($orgIds as $orgId) {
            $par = $this->delinquencyService->getPAR($orgId, $threshold);
            $totalOutstanding += $par['total_outstanding'];
            $delinquentOutstanding += $par['delinquent_outstanding'];
        }

        return [
            'total_outstanding' => round($totalOutstanding, 2),
            'delinquent_outstanding' => round($delinquentOutstanding, 2),
            'par_percentage' => $totalOutstanding > 0
                ? round(($delinquentOutstanding / $totalOutstanding) * 100, 2)
                : 0,
        ];
    }
}
