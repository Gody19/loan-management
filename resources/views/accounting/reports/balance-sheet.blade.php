@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Balance Sheet</h4>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('accounting.reports.balance-sheet') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">As of Date</label>
                    <input type="date" class="form-control" name="as_of_date" value="{{ request('as_of_date', date('Y-m-d')) }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center mb-4">
        <span class="badge {{ $data['is_balanced'] ? 'bg-success' : 'bg-danger' }} fs-6">
            {{ $data['is_balanced'] ? 'BALANCED' : 'UNBALANCED' }}
        </span>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white"><h6 class="mb-0">Assets</h6></div>
                <div class="card-body">
                    @foreach($data['assets'] as $asset)
                        <div class="d-flex justify-content-between py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span>{{ $asset['name'] }}</span>
                            <span>TSh {{ number_format($asset['balance'], 2) }}</span>
                        </div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Assets</span>
                        <span>TSh {{ number_format($data['total_assets'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white"><h6 class="mb-0">Liabilities</h6></div>
                <div class="card-body">
                    @foreach($data['liabilities'] as $liability)
                        <div class="d-flex justify-content-between py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span>{{ $liability['name'] }}</span>
                            <span>TSh {{ number_format($liability['balance'], 2) }}</span>
                        </div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Liabilities</span>
                        <span>TSh {{ number_format($data['total_liabilities'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white"><h6 class="mb-0">Equity</h6></div>
                <div class="card-body">
                    @foreach($data['equity'] as $eq)
                        <div class="d-flex justify-content-between py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span>{{ $eq['name'] }}</span>
                            <span>TSh {{ number_format($eq['balance'], 2) }}</span>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Net Income</span>
                        <span>TSh {{ number_format($data['net_income'], 2) }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Equity</span>
                        <span>TSh {{ number_format($data['total_equity'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body text-center">
            <h5>Assets = Liabilities + Equity</h5>
            <h4 class="{{ $data['is_balanced'] ? 'text-success' : 'text-danger' }}">
                TSh {{ number_format($data['total_assets'], 2) }} = TSh {{ number_format($data['total_liabilities'] + $data['total_equity'], 2) }}
            </h4>
        </div>
    </div>
</div>
@endsection
