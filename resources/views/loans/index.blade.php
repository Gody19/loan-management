@extends('layouts.app')

@section('title', 'Loans')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Loans',
        'subtitle' => 'Manage loan accounts',
        'breadcrumb' => [
            ['label' => 'Loans'],
        ],
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('loans.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by loan number or member..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\LoanStatus::values() as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ \App\Enums\LoanStatus::from($s)->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel"></i></button>
                    <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan #</th>
                        <th>Member</th>
                        <th>Loan Plan</th>
                        <th>Principal</th>
                        <th>Outstanding</th>
                        <th>Installments</th>
                        <th>Status</th>
                        <th>Disbursed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        <tr>
                            <td><span class="badge bg-primary">{{ $loan->loan_number }}</span></td>
                            <td>
                                <div class="fw-medium">{{ $loan->member->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $loan->member->member_number ?? '' }}</small>
                            </td>
                            <td>{{ $loan->loanPlan->name ?? '—' }}</td>
                            <td class="fw-medium">{{ number_format($loan->principal_amount, 0) }} TZS</td>
                            <td class="fw-medium text-danger">{{ number_format($loan->outstanding_balance, 0) }} TZS</td>
                            <td>{{ $loan->installments_paid }} / {{ $loan->total_installments }}</td>
                            <td><span class="badge bg-{{ $loan->status->color() }}">{{ $loan->status->label() }}</span></td>
                            <td><small class="text-muted">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('loans.schedule', $loan) }}" class="btn btn-outline-info" title="Schedule">
                                        <i class="bi bi-calendar3"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                                No loans found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $loans->firstItem() ?? 0 }} to {{ $loans->lastItem() ?? 0 }} of {{ $loans->total() }} loans
            </div>
            {{ $loans->links() }}
        </div>

    </div>
</div>
@endsection
