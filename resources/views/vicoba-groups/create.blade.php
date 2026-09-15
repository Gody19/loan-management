@extends('layouts.app')

@section('title', 'Create VICOBA Group - ' . config('app.name'))

@section('breadcrumb')
    @include('layouts.components.breadcrumb', ['breadcrumbs' => ['Dashboard' => route('dashboard'), 'VICOBA Groups' => route('vicoba-groups.index'), 'Create' => '']])
@endsection

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Create VICOBA Group',
        'subtitle' => 'Add a new VICOBA group',
    ])
@endsection

@section('content')

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('vicoba-groups.store') }}" data-validate>
            @csrf

            <div class="card vicoba-card mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Group Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="branch_id" class="form-label">Branch <span class="text-danger">*</span></label>
                            <select class="form-select @error('branch_id') is-invalid @enderror" id="branch_id" name="branch_id">
                                <option value="">Select Branch...</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id', request('branch_id')) == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }} ({{ $branch->organization->name }})
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="code" class="form-label">Group Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror"
                                   id="code" name="code" value="{{ old('code') }}">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name" class="form-label">Group Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="meeting_day" class="form-label">Meeting Day</label>
                            <select class="form-select @error('meeting_day') is-invalid @enderror" id="meeting_day" name="meeting_day">
                                <option value="">Select Day...</option>
                                @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                    <option value="{{ $day }}" {{ old('meeting_day') === $day ? 'selected' : '' }}>{{ $day }}</option>
                                @endforeach
                            </select>
                            @error('meeting_day')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="meeting_time" class="form-label">Meeting Time</label>
                            <input type="time" class="form-control @error('meeting_time') is-invalid @enderror"
                                   id="meeting_time" name="meeting_time" value="{{ old('meeting_time') }}">
                            @error('meeting_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                @foreach(\App\Enums\GroupStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ old('status', 'active') === $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="meeting_location" class="form-label">Meeting Location</label>
                            <input type="text" class="form-control @error('meeting_location') is-invalid @enderror"
                                   id="meeting_location" name="meeting_location" value="{{ old('meeting_location') }}">
                            @error('meeting_location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary vicoba-btn">
                    <i class="bi bi-check-lg me-1"></i> Create Group
                </button>
                <a href="{{ route('vicoba-groups.index') }}" class="btn btn-outline-secondary vicoba-btn">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
