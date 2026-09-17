@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">General Ledger</h4>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('accounting.reports.general-ledger') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Account</label>
                    <select name="account_id" class="form-select">
                        <option value="">All Accounts</option>
                        @foreach($accounts as $account)
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Journal #</th>
                            <th>Description</th>
                            <th>Account</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['entries'] as $entry)
                            <tr>
                                <td>{{ $entry['date'] instanceof \Carbon\Carbon ? $entry['date']->format('d M Y') : $entry['date'] }}</td>
                                <td>{{ $entry['journal_number'] }}</td>
                                <td>{{ Str::limit($entry['description'], 40) }}</td>
                                <td><code>{{ $entry['account_code'] }}</code> {{ $entry['account_name'] }}</td>
                                <td class="text-end">{{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' }}</td>
                                <td class="text-end">{{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' }}</td>
                                <td class="text-end fw-bold {{ $entry['running_balance'] >= 0 ? 'text-success' : 'text-danger' }}">TSh {{ number_format($entry['running_balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No entries found.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4">Total</th>
                            <th class="text-end">TSh {{ number_format($data['total_debit'], 2) }}</th>
                            <th class="text-end">TSh {{ number_format($data['total_credit'], 2) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
