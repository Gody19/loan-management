@extends('layouts.app')

@section('title', 'Welfare Funds')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Welfare Funds',
        'subtitle' => 'Manage welfare funds and contributions',
        'breadcrumb' => [
            ['label' => 'Welfare Funds'],
        ],
        'actions' => '<a href="' . route('welfare-funds.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Fund
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('welfare-funds.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search funds..." value="{{ request('search') }}">
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
                    <a href="{{ route('welfare-funds.index') }}" class="btn btn-outline-secondary">
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
                        <th class="text-end">Default Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($funds as $fund)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $fund->name }}</div>
                                <small class="text-muted">{{ Str::limit($fund->description, 50) ?? '—' }}</small>
                            </td>
                            <td><span class="badge bg-primary">{{ $fund->code }}</span></td>
                            <td>{{ ucfirst($fund->contribution_type ?? '—') }}</td>
                            <td class="text-end">{{ number_format($fund->default_amount, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $fund->status->color() }}">
                                    {{ $fund->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('welfare-funds.show', $fund) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('welfare-funds.edit', $fund) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-heart fs-1 d-block mb-2"></i>
                                No welfare funds found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $funds->firstItem() ?? 0 }} to {{ $funds->lastItem() ?? 0 }} of {{ $funds->total() }} funds
            </div>
            {{ $funds->links() }}
        </div>

    </div>
</div>
@endsection
