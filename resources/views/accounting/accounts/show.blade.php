@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Account: {{ $account->account_code }} - {{ $account->account_name }}</h4>
                <div>
                    <a href="{{ route('accounting.accounts.edit', $account->id) }}" class="btn btn-warning"><i class="fas fa-edit me-1"></i> Edit</a>
                    <a href="{{ route('accounting.accounts.index') }}" class="btn btn-outline-secondary ms-2"><i class="fas fa-arrow-left me-1"></i> Back</a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header"><h6 class="mb-0">Account Details</h6></div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr><th width="150">Code</th><td><code>{{ $account->account_code }}</code></td></tr>
                                <tr><th>Name</th><td>{{ $account->account_name }}</td></tr>
                                <tr><th>Type</th><td><span class="badge bg-{{ $account->account_type->color() }}">{{ $account->account_type->label() }}</span></td></tr>
                                <tr><th>Normal Balance</th><td>{{ ucfirst($account->normal_balance) }}</td></tr>
                                <tr><th>Parent</th><td>{{ $account->parent ? $account->parent->account_code . ' - ' . $account->parent->account_name : 'None' }}</td></tr>
                                <tr><th>Description</th><td>{{ $account->description ?? '-' }}</td></tr>
                                <tr><th>Status</th><td>{{ $account->is_active ? 'Active' : 'Inactive' }}</td></tr>
                                <tr><th>System</th><td>{{ $account->is_system ? 'Yes' : 'No' }}</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header"><h6 class="mb-0">Sub-Accounts ({{ $account->children->count() }})</h6></div>
                        <div class="card-body">
                            @forelse($account->children as $child)
                                <div class="d-flex justify-content-between align-items-center py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <span><code>{{ $child->account_code }}</code> {{ $child->account_name }}</span>
                                    <span class="badge bg-{{ $child->is_active ? 'success' : 'secondary' }}">{{ $child->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No sub-accounts.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
