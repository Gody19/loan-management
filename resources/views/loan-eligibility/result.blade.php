@extends('layouts.app')

@section('title', 'Eligibility Result')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Eligibility Result',
        'subtitle' => $result->eligible ? 'Member is ELIGIBLE' : 'Member is NOT eligible',
        'breadcrumb' => [
            ['label' => 'Loans', 'url' => '#'],
            ['label' => 'Eligibility Check', 'url' => route('loan-eligibility.index')],
            ['label' => 'Result'],
        ],
        'actions' => '<a href="' . route('loan-eligibility.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Check
        </a>'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Result Summary --}}
        <div class="card border-0 shadow-sm mb-4 {{ $result->eligible ? 'border-start border-success border-4' : 'border-start border-danger border-4' }}">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3 {{ $result->eligible ? 'bg-success' : 'bg-danger' }}" style="width: 64px; height: 64px;">
                        <i class="bi {{ $result->eligible ? 'bi-check-circle' : 'bi-x-circle' }} text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold {{ $result->eligible ? 'text-success' : 'text-danger' }}">
                            {{ $result->eligible ? 'ELIGIBLE' : 'NOT ELIGIBLE' }}
                        </h4>
                        <div class="text-muted mt-1">
                            {{ $result->memberName }} for {{ $result->planName }} — Requested {{ number_format($result->requestedAmount, 2) }} TZS
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Checks Passed</div>
                        <div class="fw-bold fs-4">{{ $result->passCount() }}/{{ $result->totalChecks() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- Check Details --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2"></i>Eligibility Checks</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            @foreach($result->checks as $checkName => $status)
                                <tr>
                                    <td class="text-muted" style="width:60%">{{ ucwords(str_replace('_', ' ', $checkName)) }}</td>
                                    <td>
                                        @if($status === 'pass')
                                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Pass</span>
                                        @else
                                            <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Fail</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>

            {{-- Financial Summary --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2"></i>Financial Summary</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted" style="width:55%">Total Savings</td><td class="fw-medium">{{ number_format($result->totalSavings, 2) }} TZS</td></tr>
                            <tr><td class="text-muted">Total Shares</td><td class="fw-medium">{{ number_format($result->totalShares, 2) }} TZS</td></tr>
                            <tr><td class="text-muted">Active Loans</td><td class="fw-medium">{{ $result->activeLoanCount }}</td></tr>
                            <tr><td class="text-muted">Max by Savings (80% rule)</td><td class="fw-medium">{{ number_format($result->maxAllowedBySavings, 2) }} TZS</td></tr>
                            <tr><td class="text-muted">Max by Shares (80% rule)</td><td class="fw-medium">{{ number_format($result->maxAllowedByShares, 2) }} TZS</td></tr>
                            <tr><td class="text-muted">Requested Amount</td><td class="fw-bold">{{ number_format($result->requestedAmount, 2) }} TZS</td></tr>
                            @if($result->eligible)
                            <tr><td class="text-muted">Approved Amount</td><td class="fw-bold text-success">{{ number_format($result->approvedAmount, 2) }} TZS</td></tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- Failure Reasons --}}
            @if(!$result->eligible && count($result->failureReasons) > 0)
            <div class="col-12">
                <div class="card border-0 shadow-sm border-start border-danger border-4">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Reasons for Rejection</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            @foreach($result->failureReasons as $reason)
                                <li class="text-danger mb-1">{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
