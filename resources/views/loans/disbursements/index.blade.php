@extends('layouts.app')

@section('title', 'Loan Disbursements')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Loan Disbursements',
        'subtitle' => 'Manage loan disbursement records',
        'breadcrumb' => [
            ['label' => 'Disbursements'],
        ],
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('loan-disbursements.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\LoanDisbursementStatus::values() as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ \App\Enums\LoanDisbursementStatus::from($s)->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel"></i></button>
                    <a href="{{ route('loan-disbursements.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Disbursement #</th>
                        <th>Loan #</th>
                        <th>Member</th>
                        <th>Amount</th>
                        <th>Fees</th>
                        <th>Net Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($disbursements as $d)
                        <tr>
                            <td><span class="badge bg-info">{{ $d->disbursement_number }}</span></td>
                            <td><span class="badge bg-primary">{{ $d->loan->loan_number ?? '—' }}</span></td>
                            <td>
                                <div class="fw-medium">{{ $d->loan->member->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $d->loan->member->member_number ?? '' }}</small>
                            </td>
                            <td class="fw-medium">{{ number_format($d->amount, 0) }} TZS</td>
                            <td>{{ number_format($d->processing_fee + $d->insurance_fee, 0) }} TZS</td>
                            <td class="fw-medium">{{ number_format($d->net_amount, 0) }} TZS</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $d->disbursement_method)) }}</td>
                            <td><span class="badge bg-{{ $d->status->color() }}">{{ $d->status->label() }}</span></td>
                            <td><small class="text-muted">{{ $d->disbursement_date->format('d M Y') }}</small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('loan-disbursements.show', $d) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-cash-stack fs-1 d-block mb-2"></i>
                                No disbursements found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $disbursements->firstItem() ?? 0 }} to {{ $disbursements->lastItem() ?? 0 }} of {{ $disbursements->total() }} disbursements
            </div>
            {{ $disbursements->links() }}
        </div>

    </div>
</div>
@endsection
