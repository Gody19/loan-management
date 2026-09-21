@extends('layouts.member')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Guarantor Requests</h4>
        <p class="text-muted mb-0">Loan applications where you have been requested as a guarantor</p>
    </div>
</div>

@php
    $pendingCount = $guarantorRequests->where('status.value', 'pending')->count();
    $acceptedCount = $guarantorRequests->where('status.value', 'accepted')->count();
    $rejectedCount = $guarantorRequests->where('status.value', 'rejected')->count();
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="stat-value text-primary">{{ $pendingCount }}</div>
                <div class="stat-label text-muted">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="stat-value text-success">{{ $acceptedCount }}</div>
                <div class="stat-label text-muted">Accepted</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="stat-value text-danger">{{ $rejectedCount }}</div>
                <div class="stat-label text-muted">Rejected</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="stat-value">{{ $guarantorRequests->count() }}</div>
                <div class="stat-label text-muted">Total</div>
            </div>
        </div>
    </div>
</div>

@if($pendingCount > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Pending Requests ({{ $pendingCount }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Application #</th>
                        <th>Applicant</th>
                        <th>Loan Plan</th>
                        <th>Amount</th>
                        <th>Term</th>
                        <th>Requested</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($guarantorRequests->where('status.value', 'pending') as $request)
                    <tr>
                        <td><span class="fw-medium">{{ $request->application->application_number ?? '—' }}</span></td>
                        <td>{{ $request->application->member->full_name ?? '—' }}</td>
                        <td>{{ $request->application->loanPlan->name ?? '—' }}</td>
                        <td class="fw-medium">TSh {{ number_format($request->application->requested_amount ?? 0, 0) }}</td>
                        <td>{{ $request->application->requested_term ?? '—' }} months</td>
                        <td>{{ $request->created_at->format('d M Y') }}</td>
                        <td class="text-center">
                            <a href="{{ route('member.guarantor.request', $request) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye me-1"></i>Review
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>All Requests</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Application #</th>
                        <th>Applicant</th>
                        <th>Loan Plan</th>
                        <th>Amount</th>
                        <th>Your Guarantee</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($guarantorRequests as $request)
                    <tr>
                        <td><span class="fw-medium">{{ $request->application->application_number ?? '—' }}</span></td>
                        <td>{{ $request->application->member->full_name ?? '—' }}</td>
                        <td>{{ $request->application->loanPlan->name ?? '—' }}</td>
                        <td>TSh {{ number_format($request->application->requested_amount ?? 0, 0) }}</td>
                        <td class="fw-medium">TSh {{ number_format($request->guaranteed_amount, 0) }}</td>
                        <td>
                            @php
                                $statusColors = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger', 'withdrawn' => 'secondary'];
                                $color = $statusColors[$request->status->value] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }}">{{ $request->status->label() }}</span>
                        </td>
                        <td>{{ $request->created_at->format('d M Y') }}</td>
                        <td class="text-center">
                            <a href="{{ route('member.guarantor.request', $request) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
                            No guarantor requests found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
