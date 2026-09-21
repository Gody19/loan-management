@extends('layouts.member')

@section('title', 'My Applications - FinancePro VICOBA')
@section('page-title', 'My Applications')


@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-file-text me-2"></i>Loan Applications ({{ $applications->count() }})</h6>
    </div>
    <div class="card-body">
        @if($applications->isEmpty())
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No loan applications yet.
                <div class="mt-2">
                    <a href="{{ route('member.loans') }}" class="btn btn-primary btn-sm">Browse Loan Plans</a>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Application #</th>
                            <th>Plan</th>
                            <th class="text-end">Amount</th>
                            <th>Term</th>
                            <th>Purpose</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applications as $app)
                            <tr>
                                <td><span class="badge bg-primary">{{ $app->application_number }}</span></td>
                                <td>{{ $app->loanPlan->name ?? '—' }}</td>
                                <td class="text-end">TSh {{ number_format($app->requested_amount, 0) }}</td>
                                <td>{{ $app->requested_term }} mo</td>
                                <td>{{ $app->loan_purpose->label() }}</td>
                                <td>{{ $app->application_date?->format('d M Y') ?? '—' }}</td>
                                <td><span class="badge bg-{{ $app->status->color() }}">{{ $app->status->label() }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('member.loans.application', $app) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
