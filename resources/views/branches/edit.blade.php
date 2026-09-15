@extends('layouts.app')

@section('title', 'Edit Branch - ' . config('app.name'))

@section('breadcrumb')
    @include('layouts.components.breadcrumb', ['breadcrumbs' => ['Dashboard' => route('dashboard'), 'Branches' => route('branches.index'), 'Edit' => '']])
@endsection

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Edit Branch',
        'subtitle' => 'Update ' . $branch->name,
    ])
@endsection

@section('content')

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('branches.update', $branch) }}" data-validate>
            @csrf
            @method('PUT')

            <div class="card vicoba-card mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Branch Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
                            <select class="form-select @error('organization_id') is-invalid @enderror" id="organization_id" name="organization_id">
                                <option value="">Select Organization...</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ old('organization_id', $branch->organization_id) == $org->id ? 'selected' : '' }}>
                                        {{ $org->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('organization_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="code" class="form-label">Branch Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror"
                                   id="code" name="code" value="{{ old('code', $branch->code) }}">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $branch->name) }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                   id="phone" name="phone" value="{{ old('phone', $branch->phone) }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="manager" class="form-label">Manager</label>
                            <input type="text" class="form-control @error('manager') is-invalid @enderror"
                                   id="manager" name="manager" value="{{ old('manager', $branch->manager) }}">
                            @error('manager')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                @foreach(\App\Enums\BranchStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ old('status', $branch->status->value) === $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control @error('address') is-invalid @enderror"
                                      id="address" name="address" rows="2">{{ old('address', $branch->address) }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary vicoba-btn">
                    <i class="bi bi-check-lg me-1"></i> Update Branch
                </button>
                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary vicoba-btn">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
