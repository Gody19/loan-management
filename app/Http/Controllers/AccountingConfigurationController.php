<?php

namespace App\Http\Controllers;

use App\Services\AccountingConfigurationService;
use App\Services\ChartOfAccountsService;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;

class AccountingConfigurationController extends Controller
{
    public function __construct(
        protected AccountingConfigurationService $configService,
        protected ChartOfAccountsService $chartService,
    ) {}

    public function index()
    {
        $this->authorize('accounting.manage');

        $organization = $this->resolveOrganization();
        $mappings = $this->configService->getAllMappings($organization->id);

        return view('accounting.configuration.index', compact('organization', 'mappings'));
    }

    public function update(Request $request)
    {
        $this->authorize('accounting.manage');

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
        $orgId = session('organization_id') ?? OrganizationContext::getFirstOrganization()?->id;

        if (! $orgId) {
            abort(403, 'No organization selected.');
        }

        OrganizationContext::authorizeOrganization($orgId);

        return \App\Models\Organization::findOrFail($orgId);
    }
}
