@extends('layouts.app')

@section('title', 'Payment Methods')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Payment Methods',
        'subtitle' => 'Manage payment methods for transactions',
        'breadcrumb' => [
            ['label' => 'Payment Methods'],
        ],
        'actions' => '<a href="' . route('payment-methods.create') . '" class="btn btn-primary">
            <i class="bi bi-plus me-1"></i> Add Payment Method
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="{{ route('payment-methods.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
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
                    <a href="{{ route('payment-methods.index') }}" class="btn btn-outline-secondary">
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
                        <th>Type</th>
                        <th>Organization</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paymentMethods as $method)
                        <tr>
                            <td class="fw-medium">{{ $method->name }}</td>
                            <td><span class="badge bg-secondary">{{ $method->code }}</span></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $method->type->value)) }}</td>
                            <td>{{ $method->organization->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $method->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($method->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('payment-methods.show', $method) }}" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('payment-methods.edit', $method) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('payment-methods.destroy', $method) }}" method="POST" class="d-inline" data-confirm="Are you sure?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-credit-card fs-1 d-block mb-2"></i>
                                No payment methods found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $paymentMethods->firstItem() ?? 0 }} to {{ $paymentMethods->lastItem() ?? 0 }} of {{ $paymentMethods->total() }} payment methods
            </div>
            {{ $paymentMethods->links() }}
        </div>

    </div>
</div>
@endsection
