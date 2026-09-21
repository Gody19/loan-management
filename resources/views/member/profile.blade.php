@extends('layouts.member')

@section('title', 'My Profile - FinancePro VICOBA')
@section('page-title', 'My Profile')

@section('content')
<div class="row g-4">
    {{-- Profile Card --}}
    <div class="col-lg-4">
        <div class="card vicoba-card">
            <div class="card-body text-center">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <span class="text-white fw-bold" style="font-size: 2rem;">
                        {{ substr($member->full_name, 0, 1) }}
                    </span>
                </div>
                <h5 class="fw-bold mb-1">{{ $member->full_name }}</h5>
                <div class="text-muted mb-2">{{ $member->member_number }}</div>
                <span class="badge bg-{{ $member->membership_status->value === 'active' ? 'success' : 'secondary' }} mb-3">
                    {{ ucfirst($member->membership_status->value) }}
                </span>

                <div class="text-start mt-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-envelope text-muted me-2"></i>
                        <span class="text-muted small">{{ $member->email ?? 'Not provided' }}</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-phone text-muted me-2"></i>
                        <span class="text-muted small">{{ $member->phone }}</span>
                    </div>
                    @if($member->vicobaGroup)
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-people text-muted me-2"></i>
                        <span class="text-muted small">{{ $member->vicobaGroup->name }}</span>
                    </div>
                    @endif
                    @if($member->branch)
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-diagram-3 text-muted me-2"></i>
                        <span class="text-muted small">{{ $member->branch->name }}</span>
                    </div>
                    @endif
                </div>

                <a href="{{ route('member.profile.edit') }}" class="btn btn-primary w-100 mt-3">
                    <i class="bi bi-pencil me-1"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>

    {{-- Personal Information --}}
    <div class="col-lg-8">
        <div class="card vicoba-card mb-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Personal Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">First Name</label>
                        <div class="fw-medium">{{ $member->first_name }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Middle Name</label>
                        <div class="fw-medium">{{ $member->middle_name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Last Name</label>
                        <div class="fw-medium">{{ $member->last_name }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Gender</label>
                        <div class="fw-medium">{{ ucfirst($member->gender->value) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Date of Birth</label>
                        <div class="fw-medium">{{ $member->date_of_birth?->format('d M Y') ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Age</label>
                        <div class="fw-medium">{{ $member->age ?? '-' }} years</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Marital Status</label>
                        <div class="fw-medium">{{ $member->marital_status ? ucfirst($member->marital_status->value) : '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">National ID</label>
                        <div class="fw-medium">{{ $member->national_id ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card vicoba-card mb-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-briefcase me-2"></i>Employment Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Occupation</label>
                        <div class="fw-medium">{{ $member->occupation ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Employer / Business</label>
                        <div class="fw-medium">{{ $member->employer_or_business ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card vicoba-card">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-geo-alt me-2"></i>Contact Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Phone</label>
                        <div class="fw-medium">{{ $member->phone }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Alternate Phone</label>
                        <div class="fw-medium">{{ $member->alternate_phone ?? '-' }}</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label text-muted small">Address</label>
                        <div class="fw-medium">{{ $member->address ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Region</label>
                        <div class="fw-medium">{{ $member->region ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">District</label>
                        <div class="fw-medium">{{ $member->district ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Ward</label>
                        <div class="fw-medium">{{ $member->ward ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Street</label>
                        <div class="fw-medium">{{ $member->street ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
