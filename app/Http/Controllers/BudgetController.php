<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index()
    {
        $budgets = auth()->user()->budgets()->with('category')->latest()->paginate(10);

        return view('dashboard.user.budgets.index', compact('budgets'));
    }

    public function create()
    {
        return view('dashboard.user.budgets.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'limit_amount' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'period' => 'required|in:weekly,monthly,quarterly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['spent_amount'] = 0;
        $validated['status'] = 'active';

        Budget::create($validated);

        return redirect()->route('budget.index')->with('success', 'Budget created successfully.');
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
