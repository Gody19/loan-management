@extends('layouts.app')

@section('title', 'Share Plan Details')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $product->name,
        'subtitle' => 'Code: ' . $product->code . ' | ' . $product->status->label(),
        'actions' => '<a href="' . route('share-products.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <a href="' . route('share-products.edit', $product) . '" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Share Price</div>
                        <div class="fs-4 fw-bold text-primary">{{ number_format($product->share_price, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Min Shares</div>
                        <div class="fs-4 fw-bold">{{ number_format($product->minimum_shares) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Max Shares</div>
                        <div class="fs-4 fw-bold">{{ number_format($product->maximum_shares) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Total Accounts</div>
                        <div class="fs-4 fw-bold">{{ $product->accounts()->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Plan Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Name</div>
                        <div class="fw-medium">{{ $product->name }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Code</div>
                        <div class="fw-medium"><span class="badge bg-primary">{{ $product->code }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $product->organization->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Status</div>
                        <div><span class="badge bg-{{ $product->status->color() }}">{{ $product->status->label() }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Created By</div>
                        <div class="fw-medium">{{ $product->creator->fullname ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Last Updated</div>
                        <div class="fw-medium">{{ $product->updated_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                    @if($product->description)
                    <div class="col-12">
                        <div class="text-muted small">Description</div>
                        <div class="fw-medium">{{ $product->description }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Accounts ({{ $product->accounts()->count() }})</h6>
            </div>
            <div class="card-body">
                @if($product->accounts->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Account No.</th>
                                    <th>Member</th>
                                    <th class="text-center">Shares</th>
                                    <th class="text-end">Value</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($product->accounts as $account)
                                    <tr>
                                        <td><span class="badge bg-primary">{{ $account->account_number }}</span></td>
                                        <td>{{ $account->member->full_name ?? '—' }}</td>
                                        <td class="text-center">{{ number_format($account->total_shares) }}</td>
                                        <td class="text-end">{{ number_format($account->total_value, 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $account->status->color() }}">
                                                {{ $account->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('share-accounts.show', $account) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No accounts for this plan yet.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
