<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Services\AccountingPeriodService;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function __construct(
        protected AccountingPeriodService $periodService,
    ) {}

    public function index()
    {
        $organization = $this->resolveOrganization();
        $periods = $this->periodService->getPeriods($organization->id);

        return view('accounting.periods.index', compact('organization', 'periods'));
    }

    public function create()
    {
        $organization = $this->resolveOrganization();

        return view('accounting.periods.create', compact('organization'));
    }

    public function store(Request $request)
    {
        $organization = $this->resolveOrganization();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $period = $this->periodService->createPeriod(
            $organization->id,
            $validated['name'],
            $validated['start_date'],
            $validated['end_date'],
            auth()->id(),
        );

        return redirect()->route('accounting.periods.index')
            ->with('success', "Period '{$period->name}' created successfully.");
    }

    public function show(int $id)
    {
        $organization = $this->resolveOrganization();
        $period = AccountingPeriod::where('id', $id)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $journalEntries = $period->journalEntries()->with('lines.account')->orderByDesc('entry_date')->paginate(20);

        return view('accounting.periods.show', compact('organization', 'period', 'journalEntries'));
    }

    public function close(int $id)
    {
        $organization = $this->resolveOrganization();
        $period = AccountingPeriod::where('id', $id)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->periodService->closePeriod($period, auth()->id());

        return redirect()->route('accounting.periods.index')
            ->with('success', "Period '{$period->name}' has been closed.");
    }
}
