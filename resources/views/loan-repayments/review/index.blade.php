@extends('layouts.app')

@section('title', 'Pending Loan Repayments')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Pending Loan Repayments',
        'subtitle' => 'Repayments submitted by members awaiting approval',
        'breadcrumb' => [
            ['label' => 'Loans', 'url' => route('loans.index')],
            ['label' => 'Pending Repayments'],
        ],
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Repayment #</th>
                        <th>Member</th>
                        <th>Loan</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Submitted By</th>
                        <th>Submitted At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($repayments as $repayment)
                        <tr>
                            <td><strong>{{ $repayment->repayment_number }}</strong></td>
                            <td>{{ $repayment->member->full_name ?? 'N/A' }}<br><small class="text-muted">{{ $repayment->member->member_number ?? '' }}</small></td>
                            <td>{{ $repayment->loan->loan_number ?? 'N/A' }}<br><small class="text-muted">{{ $repayment->loan->loanPlan->name ?? '' }}</small></td>
                            <td class="fw-semibold">TSh {{ number_format($repayment->amount, 2) }}</td>
                            <td>{{ $repayment->payment_date?->format('d M Y') }}</td>
                            <td>{{ $repayment->paymentMethod->name ?? $repayment->payment_method ?? 'N/A' }}</td>
                            <td>{{ $repayment->reference_number ?? '-' }}</td>
                            <td>{{ $repayment->receiver->name ?? '-' }}</td>
                            <td>{{ $repayment->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('loan-repayments.review.show', $repayment) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                                No pending repayments to review
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($repayments->hasPages())
        <div class="card-footer bg-white">
            {{ $repayments->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
