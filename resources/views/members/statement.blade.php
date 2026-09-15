@extends('layouts.app')

@section('title', 'Financial Statement - ' . $member->member_number)

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Financial Statement',
        'subtitle' => $member->member_number . ' - ' . $member->full_name,
        'breadcrumb' => [
            ['label' => 'Members', 'url' => route('members.index')],
            ['label' => $member->full_name, 'url' => route('members.show', $member)],
            ['label' => 'Statement'],
        ],
    ])
@endsection

@section('content')
<div class="row g-3">
    {{-- Summary Cards --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                        <i class="bi bi-wallet2 text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Total Savings</div>
                        <div class="fw-bold fs-5">TSh {{ number_format($summary['total_savings'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-primary rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                        <i class="bi bi-bar-chart-line text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Total Shares</div>
                        <div class="fw-bold fs-5">{{ number_format($summary['total_shares'] ?? 0) }} shares</div>
                        <div class="text-muted small">TSh {{ number_format($summary['total_share_value'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-warning rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                        <i class="bi bi-heart text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Welfare Balance</div>
                        <div class="fw-bold fs-5">TSh {{ number_format($summary['total_welfare_balance'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('members.statement', $member) }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="savings" {{ ($type ?? 'savings') === 'savings' ? 'selected' : '' }}>Savings</option>
                            <option value="shares" {{ ($type ?? '') === 'shares' ? 'selected' : '' }}>Shares</option>
                            <option value="welfare" {{ ($type ?? '') === 'welfare' ? 'selected' : '' }}>Welfare</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Account</label>
                        <select name="account_id" class="form-select form-select-sm" required>
                            <option value="">Select Account</option>
                            @if(($type ?? 'savings') === 'savings')
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->account_number }} - {{ $acc->product->name ?? 'N/A' }}</option>
                                @endforeach
                            @elseif(($type ?? '') === 'shares')
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->account_number }} - {{ $acc->product->name ?? 'N/A' }}</option>
                                @endforeach
                            @else
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->account_number }} - {{ $acc->fund->name ?? 'N/A' }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">From</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">To</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Transactions --}}
    @if(isset($transactions) && $transactions)
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Transactions</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Transaction No</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Balance Before</th>
                                    <th>Balance After</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $transaction)
                                    <tr>
                                        <td><strong>{{ $transaction->transaction_number }}</strong></td>
                                        <td>
                                            @if($type === 'savings')
                                                @if($transaction->type === App\Enums\SavingsTransactionType::DEPOSIT)
                                                    <span class="badge bg-success-subtle text-success">{{ $transaction->type->label() }}</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">{{ $transaction->type->label() }}</span>
                                                @endif
                                            @elseif($type === 'shares')
                                                @if($transaction->type === App\Enums\ShareTransactionType::PURCHASE)
                                                    <span class="badge bg-success-subtle text-success">{{ $transaction->type->label() }}</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">{{ $transaction->type->label() }}</span>
                                                @endif
                                            @else
                                                @if($transaction->type === App\Enums\WelfareTransactionType::CONTRIBUTION)
                                                    <span class="badge bg-success-subtle text-success">{{ $transaction->type->label() }}</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning">{{ $transaction->type->label() }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td>TSh {{ number_format($transaction->amount, 2) }}</td>
                                        <td>TSh {{ number_format($transaction->balance_before, 2) }}</td>
                                        <td>TSh {{ number_format($transaction->balance_after, 2) }}</td>
                                        <td>{{ $transaction->transaction_date?->format('d M Y') }}</td>
                                        <td>
                                            @if($transaction->status === App\Enums\FinancialTransactionStatus::COMPLETED)
                                                <span class="badge bg-success-subtle text-success">Completed</span>
                                            @elseif($transaction->status === App\Enums\FinancialTransactionStatus::REVERSED)
                                                <span class="badge bg-danger-subtle text-danger">Reversed</span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning">{{ $transaction->status->label() }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route($type . '-transactions.show', $transaction) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">No transactions found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if(method_exists($transactions, 'links'))
                    <div class="card-footer bg-white">
                        {{ $transactions->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="bi bi-funnel fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Select an account and date range to view transactions</p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection