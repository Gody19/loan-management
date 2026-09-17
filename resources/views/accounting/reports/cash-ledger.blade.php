@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Cash / Bank Ledger</h4>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('accounting.reports.cash-ledger') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Account *</label>
                    <select name="account_id" class="form-select" required>
                        <option value="">Select Account</option>
                        @foreach($assetAccounts as $account)
                            <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>{{ $account->account_code }} - {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>

    @if($data)
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Opening Balance</h6>
                        <h5 class="mb-0">TSh {{ number_format($data['opening_balance'], 2) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Total Receipts</h6>
                        <h5 class="mb-0 text-success">TSh {{ number_format($data['total_receipts'], 2) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Total Payments</h6>
                        <h5 class="mb-0 text-danger">TSh {{ number_format($data['total_payments'], 2) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Closing Balance</h6>
                        <h5 class="mb-0 fw-bold">TSh {{ number_format($data['closing_balance'], 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">{{ $data['account']->account_code }} - {{ $data['account']->account_name }}</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Journal #</th>
                                <th>Description</th>
                                <th class="text-end">Receipt</th>
                                <th class="text-end">Payment</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-info">
                                <td colspan="5"><strong>Opening Balance</strong></td>
                                <td class="text-end fw-bold">TSh {{ number_format($data['opening_balance'], 2) }}</td>
                            </tr>
                            @forelse($data['entries'] as $entry)
                                <tr>
                                    <td>{{ $entry['date'] instanceof \Carbon\Carbon ? $entry['date']->format('d M Y') : $entry['date'] }}</td>
                                    <td>{{ $entry['journal_number'] }}</td>
                                    <td>{{ Str::limit($entry['description'], 40) }}</td>
                                    <td class="text-end text-success">{{ $entry['receipt'] ? number_format($entry['receipt'], 2) : '-' }}</td>
                                    <td class="text-end text-danger">{{ $entry['payment'] ? number_format($entry['payment'], 2) : '-' }}</td>
                                    <td class="text-end fw-bold">TSh {{ number_format($entry['running_balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No transactions in this period.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3">Totals</th>
                                <th class="text-end text-success">TSh {{ number_format($data['total_receipts'], 2) }}</th>
                                <th class="text-end text-danger">TSh {{ number_format($data['total_payments'], 2) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center text-muted">
                <p>Select an account and date range to generate the cash/bank ledger.</p>
            </div>
        </div>
    @endif
</div>
@endsection
