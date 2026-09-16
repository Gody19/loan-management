@extends('layouts.app')

@section('title', 'Loan Applications')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Loan Applications',
        'subtitle' => 'Manage loan applications',
        'breadcrumb' => [
            ['label' => 'Loan Applications'],
        ],
        'actions' => '<a href="' . route('loan-applications.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Application
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('loan-applications.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by member or number..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                        <option value="under_review" {{ request('status') == 'under_review' ? 'selected' : '' }}>Under Review</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
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
                    <select name="branch_id" class="form-select">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel"></i></button>
                    <a href="{{ route('loan-applications.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Application #</th>
                        <th>Member</th>
                        <th>Loan Plan</th>
                        <th>Amount</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                        <tr>
                            <td><span class="badge bg-primary">{{ $app->application_number }}</span></td>
                            <td>
                                <div class="fw-medium">{{ $app->member->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $app->member->member_number ?? '' }}</small>
                            </td>
                            <td>{{ $app->loanPlan->name ?? '—' }}</td>
                            <td class="fw-medium">{{ number_format($app->requested_amount, 0) }} TZS</td>
                            <td>{{ $app->requested_term }} mo</td>
                            <td><span class="badge bg-{{ $app->status->color() }}">{{ $app->status->label() }}</span></td>
                            <td><small class="text-muted">{{ $app->application_date }}</small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('loan-applications.show', $app) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($app->status->value === 'draft')
                                        <a href="{{ route('loan-applications.edit', $app) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                                No loan applications found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $applications->firstItem() ?? 0 }} to {{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }} applications
            </div>
            {{ $applications->links() }}
        </div>

    </div>
</div>
@endsection
