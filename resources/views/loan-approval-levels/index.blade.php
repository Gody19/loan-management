@extends('layouts.app')

@section('title', 'Loan Approval Levels')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Loan Approval Levels',
        'subtitle' => 'Manage approval thresholds',
        'breadcrumb' => [
            ['label' => 'Approval Levels'],
        ],
        'actions' => '<a href="' . route('loan-approval-levels.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Level
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('loan-approval-levels.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-4">
                    <select name="organization_id" class="form-select">
                        <option value="">All Organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}" {{ request('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel"></i></button>
                    <a href="{{ route('loan-approval-levels.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Level</th>
                        <th>Name</th>
                        <th>Organization</th>
                        <th>Amount Range</th>
                        <th>Required Permission</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($levels as $level)
                        <tr>
                            <td><span class="badge bg-primary">{{ $level->level }}</span></td>
                            <td class="fw-medium">{{ $level->name }}</td>
                            <td>{{ $level->organization->name ?? '—' }}</td>
                            <td>
                                <span class="text-muted small">{{ number_format($level->minimum_amount, 0) }}</span>
                                <span class="text-muted small"> — </span>
                                <span class="fw-medium">{{ number_format($level->maximum_amount, 0) }}</span>
                            </td>
                            <td><span class="badge bg-secondary">{{ $level->required_permission ?? '—' }}</span></td>
                            <td>
                                <span class="badge bg-{{ $level->is_active ? 'success' : 'secondary' }}">
                                    {{ $level->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('loan-approval-levels.show', $level) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('loan-approval-levels.edit', $level) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('loan-approval-levels.destroy', $level) }}" class="d-inline" onsubmit="return confirm('Delete this approval level?')">
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
                                <i class="bi bi-layers fs-1 d-block mb-2"></i>
                                No approval levels found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $levels->firstItem() ?? 0 }} to {{ $levels->lastItem() ?? 0 }} of {{ $levels->total() }} levels
            </div>
            {{ $levels->links() }}
        </div>

    </div>
</div>
@endsection
