@extends('layouts.member')

@section('title', 'My Savings - FinancePro VICOBA')
@section('page-title', 'My Savings')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card vicoba-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="stat-label">Total Savings Balance</div>
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
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#depositModal">
                    <i class="bi bi-plus-lg me-1"></i> Deposit
                </button>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                    <i class="bi bi-dash-lg me-1"></i> Withdraw
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

@if($accounts->count() > 0)
<div class="card vicoba-card mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2 text-success"></i>Savings Accounts</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account Number</th>
                        <th>Plan</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                    <tr>
                        <td class="fw-medium">{{ $account->account_number }}</td>
                        <td>{{ $account->product->name ?? '-' }}</td>
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
<div class="card vicoba-card">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Savings Activity</h6>
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
@else
<div class="card vicoba-card">
    <div class="card-body text-center py-5">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-wallet2 text-muted" style="font-size: 2rem;"></i>
        </div>
        <h6 class="text-muted fw-semibold">No savings accounts</h6>
        <p class="text-muted small mb-0">You do not have any savings accounts yet.</p>
    </div>
</div>
@endif

{{-- Deposit Modal --}}
<div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.savings.deposit') }}" id="depositForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="depositModalLabel"><i class="bi bi-plus-circle me-2 text-success"></i>Savings Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="deposit_account" class="form-label">Savings Account <span class="text-danger">*</span></label>
                        <select class="form-select @error('savings_account_id') is-invalid @enderror"
                                id="deposit_account" name="savings_account_id" required>
                            <option value="">Select account...</option>
                            @foreach($accounts->where('status.value', 'active') as $account)
                                <option value="{{ $account->id }}"
                                    {{ old('savings_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_number }} - {{ $account->product->name ?? 'Savings' }} (TSh {{ number_format($account->current_balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                        @error('savings_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="deposit_amount" class="form-label">Amount (TSh) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                               id="deposit_amount" name="amount"
                               value="{{ old('amount') }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="deposit_payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select @error('payment_method_id') is-invalid @enderror"
                                id="deposit_payment_method" name="payment_method_id" required>
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
                        <label for="deposit_date" class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('transaction_date') is-invalid @enderror"
                               id="deposit_date" name="transaction_date"
                               value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                        @error('transaction_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="deposit_reference" class="form-label">Reference</label>
                        <input type="text" class="form-control @error('reference') is-invalid @enderror"
                               id="deposit_reference" name="reference"
                               value="{{ old('reference') }}" placeholder="e.g. Receipt number">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="deposit_description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="deposit_description" name="description" rows="2"
                                  placeholder="Optional note">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="depositSubmit">
                        <i class="bi bi-check-lg me-1"></i> Confirm Deposit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Withdraw Modal --}}
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.savings.withdraw') }}" id="withdrawForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="withdrawModalLabel"><i class="bi bi-dash-circle me-2 text-danger"></i>Savings Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="withdraw_account" class="form-label">Savings Account <span class="text-danger">*</span></label>
                        <select class="form-select @error('savings_account_id') is-invalid @enderror"
                                id="withdraw_account" name="savings_account_id" required>
                            <option value="">Select account...</option>
                            @foreach($accounts->where('status.value', 'active') as $account)
                                <option value="{{ $account->id }}"
                                    data-balance="{{ $account->current_balance }}"
                                    {{ old('savings_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_number }} - {{ $account->product->name ?? 'Savings' }} (TSh {{ number_format($account->current_balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                        @error('savings_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="withdraw_amount" class="form-label">Amount (TSh) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                               id="withdraw_amount" name="amount"
                               value="{{ old('amount') }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="withdrawBalance"></div>
                    </div>

                    <div class="mb-3">
                        <label for="withdraw_payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select @error('payment_method_id') is-invalid @enderror"
                                id="withdraw_payment_method" name="payment_method_id" required>
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
                        <label for="withdraw_date" class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('transaction_date') is-invalid @enderror"
                               id="withdraw_date" name="transaction_date"
                               value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                        @error('transaction_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="withdraw_reference" class="form-label">Reference</label>
                        <input type="text" class="form-control @error('reference') is-invalid @enderror"
                               id="withdraw_reference" name="reference"
                               value="{{ old('reference') }}" placeholder="e.g. Receipt number">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="withdraw_description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="withdraw_description" name="description" rows="2"
                                  placeholder="Optional note">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="withdrawSubmit">
                        <i class="bi bi-check-lg me-1"></i> Confirm Withdrawal
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
    const withdrawAccount = document.getElementById('withdraw_account');
    const withdrawBalance = document.getElementById('withdrawBalance');

    if (withdrawAccount) {
        withdrawAccount.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const balance = selected.getAttribute('data-balance');
            if (balance) {
                withdrawBalance.textContent = 'Available balance: TSh ' + parseFloat(balance).toLocaleString('en', {minimumFractionDigits: 2});
            } else {
                withdrawBalance.textContent = '';
            }
        });
    }

    ['depositForm', 'withdrawForm'].forEach(function(formId) {
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
