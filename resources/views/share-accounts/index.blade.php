@extends('layouts.app')

@section('title', 'Member Share Accounts')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Member Share Accounts',
        'subtitle' => 'Manage member share accounts',
        'actions' => '<a href="' . route('share-accounts.create') . '" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Open Account
        </a>'
    ])
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <form method="GET" action="{{ route('share-accounts.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <input type="text" name="search" class="form-control" placeholder="Search accounts..." value="{{ request('search') }}">
                </div>
                <div class="col-lg-2">
                    <select name="organization_id" class="form-select">
                        <option value="">All Organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}" {{ request('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="share_product_id" class="form-select">
                        <option value="">All Products</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ request('share_product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\ShareAccountStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('share-accounts.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account No.</th>
                        <th>Member</th>
                        <th>Product</th>
                        <th class="text-center">Shares</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td><span class="badge bg-primary">{{ $account->account_number }}</span></td>
                            <td>
                                <div class="fw-medium">{{ $account->member->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $account->member->member_number ?? '' }}</small>
                            </td>
                            <td>{{ $account->product->name ?? '—' }}</td>
                            <td class="text-center">{{ number_format($account->total_shares) }}</td>
                            <td class="text-end">{{ number_format($account->total_value, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $account->status->color() }}">
                                    {{ $account->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('share-accounts.show', $account) }}" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                                No share accounts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted" style="font-size: 0.875rem;">
                Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }} accounts
            </div>
            {{ $accounts->links() }}
        </div>

    </div>
</div>
@endsection
