<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShareProductRequest;
use App\Models\ShareProduct;
use Illuminate\Http\Request;

class ShareProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ShareProduct::class);

        $query = ShareProduct::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->latest()->paginate(15)->withQueryString();

        return view('share-products.index', compact('products'));
    }

    public function create()
    {
        $this->authorize('create', ShareProduct::class);

        return view('share-products.create');
    }

    public function store(StoreShareProductRequest $request)
    {
        $this->authorize('create', ShareProduct::class);

        $product = ShareProduct::create($request->validated());

        return redirect()->route('share-products.show', $product)
            ->with('success', 'Share product "'.$product->name.'" created successfully.');
    }

    public function show(ShareProduct $shareProduct)
    {
        $this->authorize('view', $shareProduct);

        $shareProduct->load('accounts.member', 'creator');

        return view('share-products.show', ['product' => $shareProduct]);
    }

    public function edit(ShareProduct $shareProduct)
    {
        $this->authorize('update', $shareProduct);

        return view('share-products.edit', ['product' => $shareProduct]);
    }

    public function update(StoreShareProductRequest $request, ShareProduct $shareProduct)
    {
        $this->authorize('update', $shareProduct);

        $shareProduct->update($request->validated());

        return redirect()->route('share-products.show', $shareProduct)
            ->with('success', 'Share product updated successfully.');
    }

    public function destroy(ShareProduct $shareProduct)
    {
        $this->authorize('delete', $shareProduct);

        $shareProduct->delete();

        return redirect()->route('share-products.index')
            ->with('success', 'Share product deleted successfully.');
    }
}
