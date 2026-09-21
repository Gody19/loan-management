@extends('layouts.member')

@section('title', 'Loan Statement - ' . $loan->loan_number)
@section('page-title', 'Loan Statement')


@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        {{-- Loan Summary --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="text-muted small">Loan Number</div>
                        <div class="fw-bold">{{ $loan->loan_number }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Principal</div>
                        <div class="fw-bold">TSh {{ number_format($loan->principal_amount, 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Outstanding</div>
                        <div class="fw-bold text-danger">TSh {{ number_format($loan->outstanding_balance, 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Status</div>
                        <div class="fw-bold"><span class="badge bg-{{ $loan->status->color() }}">{{ $loan->status->label() }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statement --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-file-text me-2"></i>Loan Statement</h6>
                <span class="badge bg-primary">{{ $statementLines->count() }} transactions</span>
            </div>
            <div class="card-body p-0">
                @if($statementLines->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No transactions recorded yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Reference</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($statementLines as $line)
                                    <tr class="{{ ($line['reversed'] ?? false) ? 'table-warning' : '' }}">
                                        <td>{{ $line['date'] ? \Carbon\Carbon::parse($line['date'])->format('d M Y') : '—' }}</td>
                                        <td class="fw-medium">
                                            {{ $line['description'] }}
                                            @if($line['reversed'] ?? false)
                                                <span class="badge bg-warning text-dark ms-1">Reversed</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $line['reference'] }}</td>
                                        <td class="text-end text-danger">
                                            @if($line['debit'] > 0)
                                                TSh {{ number_format($line['debit'], 0) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end text-success">
                                            @if($line['credit'] > 0)
                                                TSh {{ number_format($line['credit'], 0) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">
                                            TSh {{ number_format($line['balance'], 0) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('member.loans.show', $loan) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Loan
            </a>
        </div>
    </div>
</div>
@endsection
