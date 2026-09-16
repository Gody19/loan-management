@extends('layouts.app')

@section('title', 'Welfare Fund Details')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $fund->name,
        'subtitle' => 'Code: ' . $fund->code . ' | ' . $fund->status->label(),
        'breadcrumb' => [
            ['label' => 'Welfare Funds', 'url' => route('welfare-funds.index')],
            ['label' => $fund->name],
        ],
        'actions' => '<a href="' . route('welfare-funds.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <a href="' . route('welfare-funds.edit', $fund) . '" class="btn btn-warning">
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
                        <div class="text-muted small mb-1">Default Amount</div>
                        <div class="fs-4 fw-bold text-primary">{{ number_format($fund->default_amount, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Contribution Type</div>
                        <div class="fs-5 fw-bold">{{ ucfirst($fund->contribution_type ?? '—') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Total Accounts</div>
                        <div class="fs-4 fw-bold">{{ $fund->accounts()->count() }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Status</div>
                        <div><span class="badge bg-{{ $fund->status->color() }} fs-6">{{ $fund->status->label() }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Fund Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Name</div>
                        <div class="fw-medium">{{ $fund->name }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Code</div>
                        <div class="fw-medium"><span class="badge bg-primary">{{ $fund->code }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $fund->organization->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Created By</div>
                        <div class="fw-medium">{{ $fund->creator->fullname ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Last Updated</div>
                        <div class="fw-medium">{{ $fund->updated_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                    @if($fund->description)
                    <div class="col-12">
                        <div class="text-muted small">Description</div>
                        <div class="fw-medium">{{ $fund->description }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Accounts ({{ $fund->accounts()->count() }})</h6>
            </div>
            <div class="card-body">
                @if($fund->accounts->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Account No.</th>
                                    <th>Member</th>
                                    <th class="text-end">Balance</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($fund->accounts as $account)
                                    <tr>
                                        <td><span class="badge bg-primary">{{ $account->account_number }}</span></td>
                                        <td>{{ $account->member->full_name ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($account->current_balance, 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $account->status->color() }}">
                                                {{ $account->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('welfare-accounts.show', $account) }}" class="btn btn-sm btn-outline-primary">
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
                        No accounts for this fund yet.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
