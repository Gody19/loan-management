<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Services\AuditService;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $query = PaymentMethod::with('organization');

        OrganizationContext::scopeToUserOrganizations($query, $request->user());

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                    ->orWhere('code', 'LIKE', "%{$request->search}%");
            });
        }
        if ($request->filled('organization_id')) $query->where('organization_id', $request->organization_id);
        if ($request->filled('status')) $query->where('status', $request->status);

        $paymentMethods = $query->latest()->paginate(15)->withQueryString();
        $organizations = OrganizationContext::scopedOrganizations($request->user())->active()->get();

        return view('payment-methods.index', compact('paymentMethods', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', PaymentMethod::class);
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();
        return view('payment-methods.create', compact('organizations'));
    }

    public function store(StorePaymentMethodRequest $request)
    {
        $this->authorize('create', PaymentMethod::class);

        OrganizationContext::authorizeOrganization((int) $request->organization_id);

        $paymentMethod = PaymentMethod::create($request->validated());
        $this->audit->log('payment_method.created', $paymentMethod, [], $paymentMethod->toArray());
        return redirect()->route('payment-methods.index')->with('success', 'Payment method created.');
    }

    public function show(PaymentMethod $paymentMethod)
    {
        $this->authorize('view', $paymentMethod);
        return view('payment-methods.show', ['paymentMethod' => $paymentMethod]);
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        $this->authorize('update', $paymentMethod);
        $organizations = OrganizationContext::scopedOrganizations()->active()->get();
        return view('payment-methods.edit', ['paymentMethod' => $paymentMethod, 'organizations' => $organizations]);
    }

    public function update(StorePaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $this->authorize('update', $paymentMethod);
        $old = $paymentMethod->only(array_keys($request->validated()));
        $paymentMethod->update($request->validated());
        $this->audit->log('payment_method.updated', $paymentMethod, $old, $paymentMethod->toArray());
        return redirect()->route('payment-methods.index')->with('success', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $this->authorize('delete', $paymentMethod);
        $paymentMethod->delete();
        $this->audit->log('payment_method.deleted', $paymentMethod);
        return redirect()->route('payment-methods.index')->with('success', 'Payment method deleted.');
    }
}
