<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavingsProductRequest;
use App\Models\Organization;
use App\Models\SavingsProduct;
use App\Services\AuditService;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;

class SavingsProductController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', SavingsProduct::class);

        $query = SavingsProduct::with('organization');

        OrganizationContext::scopeToUserOrganizations($query, $request->user());

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                    ->orWhere('code', 'LIKE', "%{$request->search}%");
            });
        }
        if ($request->filled('organization_id')) $query->where('organization_id', $request->organization_id);
        if ($request->filled('status')) $query->where('status', $request->status);

        $products = $query->latest()->paginate(15)->withQueryString();
        $organizations = OrganizationContext::scopedOrganizations($request->user())->active()->get();

        return view('savings-products.index', compact('products', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', SavingsProduct::class);
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();
        return view('savings-products.create', compact('organizations'));
    }

    public function store(StoreSavingsProductRequest $request)
    {
        $this->authorize('create', SavingsProduct::class);

        OrganizationContext::authorizeOrganization((int) $request->organization_id);

        $product = SavingsProduct::create($request->validated());
        $this->audit->log('savings_product.created', $product, [], $product->toArray());
        return redirect()->route('savings-products.index')->with('success', 'Savings product created.');
    }

    public function show(SavingsProduct $savingsProduct)
    {
        $this->authorize('view', $savingsProduct);
        $savingsProduct->load(['organization', 'accounts.member']);
        return view('savings-products.show', ['product' => $savingsProduct]);
    }

    public function edit(SavingsProduct $savingsProduct)
    {
        $this->authorize('update', $savingsProduct);
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();
        return view('savings-products.edit', ['product' => $savingsProduct, 'organizations' => $organizations]);
    }

    public function update(StoreSavingsProductRequest $request, SavingsProduct $savingsProduct)
    {
        $this->authorize('update', $savingsProduct);
        $old = $savingsProduct->only(array_keys($request->validated()));
        $savingsProduct->update($request->validated());
        $this->audit->log('savings_product.updated', $savingsProduct, $old, $savingsProduct->toArray());
        return redirect()->route('savings-products.index')->with('success', 'Savings product updated.');
    }

    public function destroy(SavingsProduct $savingsProduct)
    {
        $this->authorize('delete', $savingsProduct);
        $savingsProduct->delete();
        $this->audit->log('savings_product.deleted', $savingsProduct);
        return redirect()->route('savings-products.index')->with('success', 'Savings product deleted.');
    }
}
