@extends('layouts.member')

@section('title', 'My Guarantees')
@section('page-title', 'My Guarantees')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-{{ $eligibility['eligible'] ? 'success' : 'warning' }} rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-shield-{{ $eligibility['eligible'] ? 'check' : 'exclamation' }} text-white fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-0 fw-bold">Guarantor Status</h5>
                        @if($eligibility['eligible'])
                            <span class="text-success fw-medium">You are eligible to guarantee another member.</span>
                        @else
                            <span class="text-warning fw-medium">{{ $eligibility['reason'] }}</span>
                        @endif
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Active Guarantees</div>
                        <div class="fw-bold fs-3 text-{{ $eligibility['active_count'] > 0 ? 'warning' : 'success' }}">{{ $eligibility['active_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Active Guarantees ({{ $activeGuarantees->count() }})</h6>
            </div>
            <div class="card-body p-0">
                @forelse($activeGuarantees as $g)
                <div class="border-bottom p-4 {{ $loop->last ? 'border-bottom-0' : '' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fw-medium">{{ $g->application->member->full_name ?? '—' }}</div>
                            <small class="text-muted">Applicant: {{ $g->application->member->member_number ?? '—' }}</small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Loan</small>
                            <span class="fw-medium">{{ $g->application->loan?->loan_number ?? 'Pending' }}</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <small class="text-muted d-block">Your Guarantee</small>
                            <span class="fw-bold text-primary">TSh {{ number_format($g->guaranteed_amount, 0) }}</span>
                        </div>
                    </div>
                    @if($g->application->loan)
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <small class="text-muted">Outstanding: </small>
                            <span class="fw-medium text-danger">TSh {{ number_format($g->application->loan->outstanding_balance, 0) }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Status: </small>
                            <span class="badge bg-{{ $g->application->loan->status->color() }}">{{ $g->application->loan->status->label() }}</span>
                        </div>
                    </div>
                    @endif
                </div>
                @empty
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
                    No active guarantees.
                </div>
                @endforelse
            </div>
        </div>

        @if($completedGuarantees->count() > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2"></i>Completed Guarantees ({{ $completedGuarantees->count() }})</h6>
            </div>
            <div class="card-body p-0">
                @foreach($completedGuarantees as $g)
                <div class="border-bottom p-4 {{ $loop->last ? 'border-bottom-0' : '' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fw-medium">{{ $g->application->member->full_name ?? '—' }}</div>
                            <small class="text-muted">Applicant: {{ $g->application->member->member_number ?? '—' }}</small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Loan</small>
                            <span class="fw-medium">{{ $g->application->loan?->loan_number ?? '—' }}</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="badge bg-secondary">Released</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
