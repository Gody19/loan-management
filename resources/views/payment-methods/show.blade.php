@extends('layouts.app')

@section('title', 'Payment Method Details')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $paymentMethod->name,
        'subtitle' => 'Payment method details',
        'breadcrumb' => [
            ['label' => 'Payment Methods', 'url' => route('payment-methods.index')],
            ['label' => $paymentMethod->name],
        ],
        'actions' => '<a href="' . route('payment-methods.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Payment Method Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Name</div>
                        <div class="fw-medium">{{ $paymentMethod->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Code</div>
                        <div class="fw-medium"><span class="badge bg-secondary">{{ $paymentMethod->code }}</span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Type</div>
                        <div class="fw-medium">{{ $paymentMethod->type->label() }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $paymentMethod->organization->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div>
                            <span class="badge bg-{{ $paymentMethod->status === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($paymentMethod->status) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Created</div>
                        <div class="fw-medium">{{ $paymentMethod->created_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('payment-methods.edit', $paymentMethod) }}" class="btn btn-warning">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <form action="{{ route('payment-methods.destroy', $paymentMethod) }}" method="POST" class="d-inline" data-confirm="Are you sure you want to delete this payment method?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>

    </div>
</div>
@endsection
