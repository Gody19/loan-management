@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Trial Balance</h4>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('accounting.reports.trial-balance') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="text-center mb-3">
                <span class="badge {{ $data['is_balanced'] ? 'bg-success' : 'bg-danger' }} fs-6">
                    {{ $data['is_balanced'] ? 'BALANCED' : 'UNBALANCED' }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th class="text-end">Debit Balance</th>
                            <th class="text-end">Credit Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['accounts'] as $account)
                            <tr>
                                <td><code>{{ $account['account_code'] }}</code></td>
                                <td>{{ $account['account_name'] }}</td>
                                <td>{{ $account['account_type'] }}</td>
                                <td class="text-end">{{ $account['debit_balance'] > 0 ? number_format($account['debit_balance'], 2) : '-' }}</td>
                                <td class="text-end">{{ $account['credit_balance'] > 0 ? number_format($account['credit_balance'], 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3">Total</th>
                            <th class="text-end">TSh {{ number_format($data['total_debit'], 2) }}</th>
                            <th class="text-end">TSh {{ number_format($data['total_credit'], 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
