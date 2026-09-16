@extends('layouts.app')

@section('title', 'Savings Plans')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Savings Plans',
        'subtitle' => 'Manage savings plan offerings',
        'breadcrumb' => [
            ['label' => 'Savings Plans'],
        ],
        'actions' => '<a href="' . route('savings-products.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Plan
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        {{-- Filters --}}
        <form method="GET" action="{{ route('savings-products.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-3">
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
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('savings-products.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Organization</th>
                        <th>Min Amount</th>
                        <th>Max Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $product->name }}</div>
                                <small class="text-muted">{{ Str::limit($product->description, 40) ?? '—' }}</small>
                            </td>
                            <td><span class="badge bg-primary">{{ $product->code }}</span></td>
                            <td>{{ $product->organization->name ?? '—' }}</td>
                            <td>{{ number_format($product->minimum_amount, 2) }}</td>
                            <td>{{ number_format($product->maximum_amount, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $product->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($product->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('savings-products.show', $product) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('savings-products.edit', $product) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('savings-products.destroy', $product) }}" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-piggy-bank fs-1 d-block mb-2"></i>
                                No savings products found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products
            </div>
            {{ $products->links() }}
        </div>

    </div>
</div>
@endsection
