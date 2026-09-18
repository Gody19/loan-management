@extends('layouts.member')

@section('title', 'My Welfare - FinancePro VICOBA')
@section('page-title', 'My Welfare')

@section('breadcrumb')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('member.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">My Welfare</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-heart"></i></div>
                    <div>
                        <div class="stat-label">Total Welfare Balance</div>
                        <div class="stat-value">TSh {{ number_format($accounts->sum('current_balance'), 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-folder2-open"></i></div>
                    <div>
                        <div class="stat-label">Active Accounts</div>
                        <div class="stat-value">{{ $accounts->where('status.value', 'active')->count() }}</div>
                        <small class="text-muted">of {{ $accounts->count() }} total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($accounts->where('status.value', 'active')->count() > 0)
    <div class="col-xl-4 col-md-12">
        <div class="card vicoba-card h-100">
            <div class="card-body d-flex align-items-center justify-content-center gap-2">
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#contributeModal">
                    <i class="bi bi-plus-lg me-1"></i> Contribute
                </button>
                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#benefitModal">
                    <i class="bi bi-file-earmark-medical me-1"></i> Request Benefit
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

@if($accounts->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-heart me-2 text-info"></i>Welfare Accounts</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account Number</th>
                        <th>Fund</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                    <tr>
                        <td class="fw-medium">{{ $account->account_number }}</td>
                        <td>{{ $account->fund->name ?? '-' }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($account->current_balance, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $account->status->value === 'active' ? 'success' : 'secondary' }}">
                                {{ $account->status->label() }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($recentTransactions->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Welfare Activity</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTransactions as $tx)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tx['date'])->format('d M Y') }}</td>
                        <td class="fw-medium">{{ $tx['transaction_number'] }}</td>
                        <td>{{ $tx['type'] }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($tx['amount'], 2) }}</td>
                        <td>
                            @php
                                $statusColors = ['Completed' => 'success', 'Pending' => 'warning', 'Reversed' => 'danger'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$tx['status']] ?? 'secondary' }}">
                                {{ $tx['status'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($benefitRequests->count() > 0)
<div class="card vicoba-card">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-medical me-2 text-warning"></i>Benefit Requests</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Request #</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($benefitRequests as $br)
                    <tr>
                        <td class="fw-medium">{{ $br->request_number }}</td>
                        <td>{{ $br->created_at->format('d M Y') }}</td>
                        <td class="text-end fw-medium">TSh {{ number_format($br->requested_amount, 2) }}</td>
                        <td>{{ \Str::limit($br->reason ?? '-', 40) }}</td>
                        <td>
                            @php
                                $brStatusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                            @endphp
                            <span class="badge bg-{{ $brStatusColors[$br->status->value] ?? 'secondary' }}">
                                {{ $br->status->label() }}
                            </span>
                            @if($br->status->value === 'rejected' && $br->rejection_reason)
                                <br><small class="text-muted">{{ \Str::limit($br->rejection_reason, 50) }}</small>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@else
<div class="card vicoba-card">
    <div class="card-body text-center py-5">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-heart text-muted" style="font-size: 2rem;"></i>
        </div>
        <h6 class="text-muted fw-semibold">No welfare accounts</h6>
        <p class="text-muted small mb-0">You do not have any welfare accounts yet.</p>
    </div>
</div>
@endif

{{-- Contribute Modal --}}
<div class="modal fade" id="contributeModal" tabindex="-1" aria-labelledby="contributeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.welfare.contribute') }}" id="contributeForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="contributeModalLabel"><i class="bi bi-plus-circle me-2 text-info"></i>Welfare Contribution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="contribute_account" class="form-label">Welfare Account <span class="text-danger">*</span></label>
                        <select class="form-select @error('welfare_account_id') is-invalid @enderror"
                                id="contribute_account" name="welfare_account_id" required>
                            <option value="">Select account...</option>
                            @foreach($accounts->where('status.value', 'active') as $account)
                                <option value="{{ $account->id }}"
                                    data-balance="{{ $account->current_balance }}"
                                    {{ old('welfare_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_number }} - {{ $account->fund->name ?? 'Welfare' }} (TSh {{ number_format($account->current_balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                        @error('welfare_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contribute_amount" class="form-label">Amount (TSh) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                               id="contribute_amount" name="amount"
                               value="{{ old('amount') }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contribute_payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select @error('payment_method_id') is-invalid @enderror"
                                id="contribute_payment_method" name="payment_method_id" required>
                            <option value="">Select payment method...</option>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}" {{ old('payment_method_id') == $method->id ? 'selected' : '' }}>
                                    {{ $method->name }} ({{ $method->type->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('payment_method_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contribute_date" class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('transaction_date') is-invalid @enderror"
                               id="contribute_date" name="transaction_date"
                               value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                        @error('transaction_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contribute_reference" class="form-label">Reference</label>
                        <input type="text" class="form-control @error('reference') is-invalid @enderror"
                               id="contribute_reference" name="reference"
                               value="{{ old('reference') }}" placeholder="e.g. Receipt number">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contribute_description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="contribute_description" name="description" rows="2"
                                  placeholder="Optional note">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info" id="contributeSubmit">
                        <i class="bi bi-check-lg me-1"></i> Confirm Contribution
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Benefit Request Modal --}}
<div class="modal fade" id="benefitModal" tabindex="-1" aria-labelledby="benefitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.welfare.benefit-request') }}" id="benefitForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="benefitModalLabel"><i class="bi bi-file-earmark-medical me-2 text-warning"></i>Request Welfare Benefit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Your request will be reviewed by an administrator. You will be notified once a decision is made.
                    </div>

                    <div class="mb-3">
                        <label for="benefit_account" class="form-label">Welfare Account <span class="text-danger">*</span></label>
                        <select class="form-select @error('welfare_account_id') is-invalid @enderror"
                                id="benefit_account" name="welfare_account_id" required>
                            <option value="">Select account...</option>
                            @foreach($accounts->where('status.value', 'active') as $account)
                                <option value="{{ $account->id }}"
                                    data-balance="{{ $account->current_balance }}"
                                    {{ old('welfare_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_number }} - {{ $account->fund->name ?? 'Welfare' }} (TSh {{ number_format($account->current_balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                        @error('welfare_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="benefitBalance"></div>
                    </div>

                    <div class="mb-3">
                        <label for="benefit_amount" class="form-label">Requested Amount (TSh) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('requested_amount') is-invalid @enderror"
                               id="benefit_amount" name="requested_amount"
                               value="{{ old('requested_amount') }}" required>
                        @error('requested_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="benefit_reason" class="form-label">Reason</label>
                        <textarea class="form-control @error('reason') is-invalid @enderror"
                                  id="benefit_reason" name="reason" rows="3"
                                  placeholder="Describe the reason for this benefit request">{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="benefitSubmit">
                        <i class="bi bi-send me-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const benefitAccount = document.getElementById('benefit_account');
    const benefitBalance = document.getElementById('benefitBalance');

    if (benefitAccount) {
        benefitAccount.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const balance = selected.getAttribute('data-balance');
            if (balance) {
                benefitBalance.textContent = 'Available balance: TSh ' + parseFloat(balance).toLocaleString('en', {minimumFractionDigits: 2});
            } else {
                benefitBalance.textContent = '';
            }
        });
    }

    ['contributeForm', 'benefitForm'].forEach(function(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function() {
                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
            });
        }
    });
});
</script>
@endpush
