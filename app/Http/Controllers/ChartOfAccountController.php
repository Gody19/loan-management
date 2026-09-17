<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\ChartOfAccount as ChartOfAccountModel;
use App\Services\ChartOfAccountsService;
use App\Services\AccountingConfigurationService;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    public function __construct(
        protected ChartOfAccountsService $chartService,
        protected AccountingConfigurationService $configService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();
        $type = $request->filled('type') ? AccountType::from($request->type) : null;

        $accounts = $this->chartService->getAccounts($organization->id, $type);

        $accountsByType = $accounts->groupBy('account_type.value');

        return view('accounting.accounts.index', compact('organization', 'accounts', 'accountsByType'));
    }

    public function create()
    {
        $this->authorize('create', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();
        $parentAccounts = $this->chartService->getAccounts($organization->id);
        $accountTypes = AccountType::cases();

        return view('accounting.accounts.create', compact('organization', 'parentAccounts', 'accountTypes'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();

        $validated = $request->validate([
            'account_code' => 'required|string|max:20',
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|string|in:' . implode(',', AccountType::values()),
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
        ]);

        $account = $this->chartService->createAccount(
            $organization->id,
            $validated['account_code'],
            $validated['account_name'],
            AccountType::from($validated['account_type']),
            $validated['description'] ?? null,
            false,
            $validated['parent_id'] ?? null,
            auth()->id(),
        );

        return redirect()->route('accounting.accounts.index')
            ->with('success', "Account '{$account->account_name}' created successfully.");
    }

    public function show(int $id)
    {
        $this->authorize('viewAny', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();
        $account = $this->chartService->getAccount($organization->id, $id);

        return view('accounting.accounts.show', compact('organization', 'account'));
    }

    public function edit(int $id)
    {
        $this->authorize('update', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();
        $account = $this->chartService->getAccount($organization->id, $id);
        $parentAccounts = $this->chartService->getAccounts($organization->id)
            ->filter(fn ($a) => $a->id !== $id);
        $accountTypes = AccountType::cases();

        return view('accounting.accounts.edit', compact('organization', 'account', 'parentAccounts', 'accountTypes'));
    }

    public function update(Request $request, int $id)
    {
        $this->authorize('update', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();
        $account = $this->chartService->getAccount($organization->id, $id);

        $validated = $request->validate([
            'account_code' => 'required|string|max:20',
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|string|in:' . implode(',', AccountType::values()),
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
        ]);

        $this->chartService->updateAccount($account, $validated, auth()->id());

        return redirect()->route('accounting.accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->authorize('delete', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();
        $account = $this->chartService->getAccount($organization->id, $id);

        $this->chartService->deleteAccount($account);

        return redirect()->route('accounting.accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    public function toggle(int $id)
    {
        $this->authorize('update', ChartOfAccountModel::class);

        $organization = $this->resolveOrganization();
        $account = $this->chartService->getAccount($organization->id, $id);

        if ($account->is_active) {
            $this->chartService->deactivateAccount($account);
            $message = 'Account deactivated.';
        } else {
            $this->chartService->activateAccount($account);
            $message = 'Account activated.';
        }

        return redirect()->route('accounting.accounts.index')
            ->with('success', $message);
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
