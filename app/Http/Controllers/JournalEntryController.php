<?php

namespace App\Http\Controllers;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Services\JournalPostingService;
use App\Services\JournalReversalService;
use App\Services\ChartOfAccountsService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(
        protected JournalPostingService $postingService,
        protected JournalReversalService $reversalService,
        protected ChartOfAccountsService $chartService,
    ) {}

    public function index(Request $request)
    {
        $organization = $this->resolveOrganization();

        $query = $this->postingService->getEntries(
            $organization->id,
            $request->status,
            $request->branch_id,
            $request->start_date,
            $request->end_date,
            $request->period_id,
        );

        $entries = $query->paginate(25);

        return view('accounting.journals.index', compact('organization', 'entries'));
    }

    public function create()
    {
        $organization = $this->resolveOrganization();
        $accounts = $this->chartService->getAccounts($organization->id);

        return view('accounting.journals.create', compact('organization', 'accounts'));
    }

    public function store(Request $request)
    {
        $organization = $this->resolveOrganization();

        $validated = $request->validate([
            'entry_date' => 'required|date',
            'description' => 'required|string|max:500',
            'branch_id' => 'nullable|exists:branches,id',
            'lines' => 'required|array|min:2',
            'lines.*.chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'organization_id' => $organization->id,
            'entry_date' => $validated['entry_date'],
            'description' => $validated['description'],
            'branch_id' => $validated['branch_id'] ?? null,
            'lines' => $validated['lines'],
        ];

        $entry = $this->postingService->createDraft($data, auth()->id());

        return redirect()->route('accounting.journals.show', $entry->id)
            ->with('success', "Journal entry {$entry->journal_number} created as draft.");
    }

    public function show(int $id)
    {
        $organization = $this->resolveOrganization();
        $entry = $this->postingService->getEntry($organization->id, $id);

        return view('accounting.journals.show', compact('organization', 'entry'));
    }

    public function post(int $id)
    {
        $organization = $this->resolveOrganization();
        $entry = $this->postingService->getEntry($organization->id, $id);

        $this->postingService->postEntry($entry, auth()->id());

        return redirect()->route('accounting.journals.show', $id)
            ->with('success', "Journal entry {$entry->journal_number} posted successfully.");
    }

    public function reverse(Request $request, int $id)
    {
        $organization = $this->resolveOrganization();
        $entry = $this->postingService->getEntry($organization->id, $id);

        $validated = $request->validate([
            'reversal_reason' => 'required|string|max:500',
        ]);

        $this->reversalService->reverseJournal($entry, $validated['reversal_reason'], auth()->id());

        return redirect()->route('accounting.journals.show', $id)
            ->with('success', 'Journal entry reversed successfully.');
    }
}
