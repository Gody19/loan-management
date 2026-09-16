@extends('layouts.app')

@section('title', $group->name . ' - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $group->name,
        'subtitle' => 'VICOBA Group Details',
        'breadcrumb' => [
            ['label' => 'VICOBA Groups', 'url' => route('vicoba-groups.index')],
            ['label' => $group->name],
        ],
        'actions' => '<a href="' . route('vicoba-groups.edit', $group) . '" class="btn btn-primary vicoba-btn"><i class="bi bi-pencil me-1"></i> Edit</a>',
    ])
@endsection

@section('content')

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card vicoba-card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3" style="font-size: 0.9rem;">
                    <div class="col-md-6">
                        <span class="text-muted d-block">Group Code</span>
                        <span class="fw-medium"><code>{{ $group->code }}</code></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Status</span>
                        <span class="badge bg-{{ $group->status->color() }}">{{ $group->status->label() }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Branch</span>
                        <a href="{{ route('branches.show', $group->branch) }}">{{ $group->branch->name }}</a>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Organization</span>
                        <span>{{ $group->branch->organization->name }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Meeting Day</span>
                        <span>{{ $group->meeting_day ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Meeting Time</span>
                        <span>{{ $group->meeting_time ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Meeting Location</span>
                        <span>{{ $group->meeting_location ?? '-' }}</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Created</span>
                        <span>{{ $group->created_at->format('M d, Y') }}</span>
                    </div>
                    @if($group->description)
                        <div class="col-md-12">
                            <span class="text-muted d-block">Description</span>
                            <span>{{ $group->description }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
