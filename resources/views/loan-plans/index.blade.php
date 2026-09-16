@extends('layouts.app')

@section('title', 'Loan Plans')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Loan Plans',
        'subtitle' => 'Manage loan plan offerings',
        'breadcrumb' => [
            ['label' => 'Loan Plans'],
        ],
        'actions' => '<a href="' . route('loan-plans.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Plan
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('loan-plans.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search plans..." value="{{ request('search') }}">
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
                <div class="col-lg-2">
                    <select name="loan_purpose" class="form-select">
                        <option value="">All Purposes</option>
                        <option value="business" {{ request('loan_purpose') == 'business' ? 'selected' : '' }}>Business</option>
                        <option value="agriculture" {{ request('loan_purpose') == 'agriculture' ? 'selected' : '' }}>Agriculture</option>
                        <option value="education" {{ request('loan_purpose') == 'education' ? 'selected' : '' }}>Education</option>
                        <option value="emergency" {{ request('loan_purpose') == 'emergency' ? 'selected' : '' }}>Emergency</option>
                        <option value="personal" {{ request('loan_purpose') == 'personal' ? 'selected' : '' }}>Personal</option>
                        <option value="development" {{ request('loan_purpose') == 'development' ? 'selected' : '' }}>Development</option>
                        <option value="other" {{ request('loan_purpose') == 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('loan-plans.index') }}" class="btn btn-outline-secondary">
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
                        <th>Organization</th>
                        <th>Purpose</th>
                        <th>Amount Range</th>
                        <th>Interest</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loanPlans as $plan)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $plan->name }}</div>
                                <small class="text-muted">{{ Str::limit($plan->description, 40) ?? '—' }}</small>
                            </td>
                            <td><span class="badge bg-primary">{{ $plan->code }}</span></td>
                            <td>{{ $plan->organization->name ?? '—' }}</td>
                            <td><span class="badge bg-info">{{ $plan->loan_purpose->label() }}</span></td>
                            <td>
                                <span class="text-muted small">{{ number_format($plan->minimum_amount, 0) }}</span>
                                <span class="text-muted small"> — </span>
                                <span class="fw-medium">{{ number_format($plan->maximum_amount, 0) }}</span>
                            </td>
                            <td>
                                <span class="fw-medium">{{ number_format($plan->interest_rate, 1) }}%</span>
                                <small class="text-muted d-block">{{ $plan->interest_method->label() }}</small>
                            </td>
                            <td>
                                <span class="badge bg-{{ $plan->status->color() }}">
                                    {{ $plan->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('loan-plans.show', $plan) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('loan-plans.edit', $plan) }}" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('loan-plans.destroy', $plan) }}" class="d-inline" data-confirm="Delete this loan plan?">
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
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                                No loan plans found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $loanPlans->firstItem() ?? 0 }} to {{ $loanPlans->lastItem() ?? 0 }} of {{ $loanPlans->total() }} plans
            </div>
            {{ $loanPlans->links() }}
        </div>

    </div>
</div>
@endsection
