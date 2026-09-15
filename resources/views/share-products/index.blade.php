@extends('layouts.app')

@section('title', 'Share Products')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Share Products',
        'subtitle' => 'Manage share products and pricing',
        'actions' => '<a href="' . route('share-products.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Product
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('share-products.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2">
                    <select name="organization_id" class="form-select">
                        <option value="">All Organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}" {{ request('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\SavingsAccountStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('share-products.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Min Shares</th>
                        <th class="text-center">Max Shares</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $product->name }}</div>
                                <small class="text-muted">{{ Str::limit($product->description, 50) ?? '—' }}</small>
                            </td>
                            <td><span class="badge bg-primary">{{ $product->code }}</span></td>
                            <td class="text-end">{{ number_format($product->share_price, 2) }}</td>
                            <td class="text-center">{{ number_format($product->minimum_shares) }}</td>
                            <td class="text-center">{{ number_format($product->maximum_shares) }}</td>
                            <td>
                                <span class="badge bg-{{ $product->status->color() }}">
                                    {{ $product->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('share-products.show', $product) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('share-products.edit', $product) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                                No share products found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products
            </div>
            {{ $products->links() }}
        </div>

    </div>
</div>
@endsection
