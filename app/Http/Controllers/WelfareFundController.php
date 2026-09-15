<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWelfareFundRequest;
use App\Models\Organization;
use App\Models\WelfareFund;
use Illuminate\Http\Request;

class WelfareFundController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', WelfareFund::class);

        $query = WelfareFund::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $organizations = Organization::active()->get();
        $funds = $query->latest()->paginate(15)->withQueryString();

        return view('welfare-funds.index', compact('funds', 'organizations'));
    }

    public function create()
    {
        $this->authorize('create', WelfareFund::class);

        $organizations = Organization::active()->get();

        return view('welfare-funds.create', compact('organizations'));
    }

    public function store(StoreWelfareFundRequest $request)
    {
        $this->authorize('create', WelfareFund::class);

        $fund = WelfareFund::create($request->validated());

        return redirect()->route('welfare-funds.show', $fund)
            ->with('success', 'Welfare fund "'.$fund->name.'" created successfully.');
    }

    public function show(WelfareFund $welfareFund)
    {
        $this->authorize('view', $welfareFund);

        $welfareFund->load('accounts.member', 'creator');

        return view('welfare-funds.show', ['fund' => $welfareFund]);
    }

    public function edit(WelfareFund $welfareFund)
    {
        $this->authorize('update', $welfareFund);

        $organizations = Organization::active()->get();

        return view('welfare-funds.edit', ['fund' => $welfareFund, 'organizations' => $organizations]);
    }

    public function update(StoreWelfareFundRequest $request, WelfareFund $welfareFund)
    {
        $this->authorize('update', $welfareFund);

        $welfareFund->update($request->validated());

        return redirect()->route('welfare-funds.show', $welfareFund)
            ->with('success', 'Welfare fund updated successfully.');
    }

    public function destroy(WelfareFund $welfareFund)
    {
        $this->authorize('delete', $welfareFund);

        $welfareFund->delete();

        return redirect()->route('welfare-funds.index')
            ->with('success', 'Welfare fund deleted successfully.');
    }
}
