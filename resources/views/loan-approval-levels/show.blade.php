@extends('layouts.app')

@section('title', 'Approval Level')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $level->name,
        'subtitle' => 'Level ' . $level->level . ' | ' . ($level->is_active ? 'Active' : 'Inactive'),
        'breadcrumb' => [
            ['label' => 'Approval Levels', 'url' => route('loan-approval-levels.index')],
            ['label' => $level->name],
        ],
        'actions' => '<a href="' . route('loan-approval-levels.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <a href="' . route('loan-approval-levels.edit', $level) . '" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><td class="text-muted" style="width:45%">Name</td><td class="fw-medium">{{ $level->name }}</td></tr>
                    <tr><td class="text-muted">Level</td><td><span class="badge bg-primary">{{ $level->level }}</span></td></tr>
                    <tr><td class="text-muted">Organization</td><td class="fw-medium">{{ $level->organization->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Minimum Amount</td><td class="fw-medium">{{ number_format($level->minimum_amount, 2) }} TZS</td></tr>
                    <tr><td class="text-muted">Maximum Amount</td><td class="fw-medium">{{ number_format($level->maximum_amount, 2) }} TZS</td></tr>
                    <tr><td class="text-muted">Required Permission</td><td><span class="badge bg-secondary">{{ $level->required_permission ?? '—' }}</span></td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $level->is_active ? 'success' : 'secondary' }}">{{ $level->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                    <tr><td class="text-muted">Created</td><td class="fw-medium">{{ $level->created_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
