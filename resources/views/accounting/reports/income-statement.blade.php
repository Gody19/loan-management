@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Income Statement</h4>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('accounting.reports.income-statement') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="{{ request('start_date', date('Y-01-01')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="{{ request('end_date', date('Y-m-d')) }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white"><h6 class="mb-0">Income</h6></div>
                <div class="card-body">
                    @foreach($data['income'] as $item)
                        <div class="d-flex justify-content-between py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span>{{ $item['name'] }}</span>
                            <span>TSh {{ number_format($item['amount'], 2) }}</span>
                        </div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Income</span>
                        <span class="text-success">TSh {{ number_format($data['total_income'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white"><h6 class="mb-0">Expenses</h6></div>
                <div class="card-body">
                    @foreach($data['expenses'] as $item)
                        <div class="d-flex justify-content-between py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span>{{ $item['name'] }}</span>
                            <span>TSh {{ number_format($item['amount'], 2) }}</span>
                        </div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Expenses</span>
                        <span class="text-danger">TSh {{ number_format($data['total_expenses'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body text-center">
            <h5>Net Income = Total Income - Total Expenses</h5>
            <h4 class="{{ $data['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">
                Net Income: TSh {{ number_format($data['net_income'], 2) }}
            </h4>
        </div>
    </div>
</div>
@endsection
