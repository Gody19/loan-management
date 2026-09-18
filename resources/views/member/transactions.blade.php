@extends('layouts.member')

@section('title', 'My Transactions - FinancePro VICOBA')
@section('page-title', 'My Transactions')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">My Transactions</li>
        </ol>
    </nav>
@endsection

@section('content')
@if($transactions->count() > 0)
<div class="card vicoba-card">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>All Financial Activity</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tx->date)->format('d M Y') }}</td>
                        <td class="fw-medium">{{ $tx->reference }}</td>
                        <td>
                            @php
                                $catColors = ['savings' => 'success', 'shares' => 'warning', 'welfare' => 'info', 'loan_repayment' => 'primary'];
                                $catLabels = ['savings' => 'Savings', 'shares' => 'Shares', 'welfare' => 'Welfare', 'loan_repayment' => 'Loan Repayment'];
                            @endphp
                            <span class="badge bg-{{ $catColors[$tx->category] ?? 'secondary' }}">
                                {{ $catLabels[$tx->category] ?? $tx->category }}
                            </span>
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $tx->type)) }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($tx->amount, 2) }}</td>
                        <td>
                            @php
                                $statusColors = ['completed' => 'success', 'confirmed' => 'success', 'pending' => 'warning', 'reversed' => 'danger', 'posted' => 'success'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$tx->status] ?? 'secondary' }}">
                                {{ ucfirst($tx->status) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer bg-transparent">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@else
<div class="card vicoba-card">
    <div class="card-body text-center py-5">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-list-ul text-muted" style="font-size: 2rem;"></i>
        </div>
        <h6 class="text-muted fw-semibold">No transactions yet</h6>
        <p class="text-muted small mb-0">Your financial activity will appear here.</p>
    </div>
</div>
@endif
@endsection
