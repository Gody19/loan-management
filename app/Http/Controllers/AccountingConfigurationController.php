<?php

namespace App\Http\Controllers;

use App\Services\AccountingConfigurationService;
use App\Services\ChartOfAccountsService;
use Illuminate\Http\Request;

class AccountingConfigurationController extends Controller
{
    public function __construct(
        protected AccountingConfigurationService $configService,
        protected ChartOfAccountsService $chartService,
    ) {}

    public function index()
    {
        $organization = $this->resolveOrganization();
        $mappings = $this->configService->getAllMappings($organization->id);

        return view('accounting.configuration.index', compact('organization', 'mappings'));
    }

    public function update(Request $request)
    {
        $organization = $this->resolveOrganization();

        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*' => 'required|exists:chart_of_accounts,id',
        ]);

        foreach ($validated['mappings'] as $key => $accountId) {
            $this->configService->setMapping($organization->id, $key, (int) $accountId);
        }

        return redirect()->route('accounting.configuration.index')
            ->with('success', 'Account mappings updated successfully.');
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
