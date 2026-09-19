@extends('layouts.app')

@section('title', 'Register New Member')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Register New Member',
        'subtitle' => 'Add a new VICOBA member',
        'breadcrumb' => [
            ['label' => 'Members', 'url' => route('members.index')],
            ['label' => 'Register'],
        ],
        'actions' => '<a href="' . route('members.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('members.store') }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-10">

            {{-- Membership --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Membership</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Organization <span class="text-danger">*</span></label>
                            <select name="organization_id" id="organization_id" class="form-select" required>
                                <option value="">Select Organization</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('organization_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" id="branch_id" class="form-select" required>
                                <option value="">Select Branch</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" data-org="{{ $branch->organization_id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">VICOBA Group <span class="text-danger">*</span></label>
                            <select name="vicoba_group_id" id="vicoba_group_id" class="form-select" required>
                                <option value="">Select Group</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" data-branch="{{ $group->branch_id }}" {{ old('vicoba_group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                                @endforeach
                            </select>
                            @error('vicoba_group_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Personal Information --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                            @error('first_name') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" value="{{ old('middle_name') }}">
                            @error('middle_name') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
                            @error('last_name') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                @foreach(\App\Enums\Gender::cases() as $g)
                                    <option value="{{ $g->value }}" {{ old('gender') == $g->value ? 'selected' : '' }}>{{ $g->label() }}</option>
                                @endforeach
                            </select>
                            @error('gender') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}">
                            @error('date_of_birth') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marital Status</label>
                            <select name="marital_status" class="form-select">
                                <option value="">Select</option>
                                @foreach(\App\Enums\MaritalStatus::cases() as $ms)
                                    <option value="{{ $ms->value }}" {{ old('marital_status') == $ms->value ? 'selected' : '' }}>{{ $ms->label() }}</option>
                                @endforeach
                            </select>
                            @error('marital_status') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contact Information --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-telephone me-2"></i>Contact Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="+2557XXXXXXXX" required>
                            @error('phone') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alternate Phone</label>
                            <input type="text" name="alternate_phone" class="form-control" value="{{ old('alternate_phone') }}">
                            @error('alternate_phone') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                            @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                            <small class="text-muted">Used for portal login credentials. Default password: <strong>password</strong></small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-geo-alt me-2"></i>Address</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Region</label>
                            <input type="text" name="region" class="form-control" value="{{ old('region') }}">
                            @error('region') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">District</label>
                            <input type="text" name="district" class="form-control" value="{{ old('district') }}">
                            @error('district') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ward</label>
                            <input type="text" name="ward" class="form-control" value="{{ old('ward') }}">
                            @error('ward') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Street</label>
                            <input type="text" name="street" class="form-control" value="{{ old('street') }}">
                            @error('street') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                            @error('address') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Employment / Business --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-briefcase me-2"></i>Employment / Business</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="occupation" class="form-control" value="{{ old('occupation') }}">
                            @error('occupation') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employer / Business</label>
                            <input type="text" name="employer_or_business" class="form-control" value="{{ old('employer_or_business') }}">
                            @error('employer_or_business') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Registration Details --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-check me-2"></i>Registration Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" name="joining_date" class="form-control" value="{{ old('joining_date', date('Y-m-d')) }}" required>
                            @error('joining_date') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="membership_status" class="form-select">
                                @foreach(\App\Enums\MemberStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ old('membership_status', 'pending') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('membership_status') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">National ID</label>
                            <input type="text" name="national_id" class="form-control" value="{{ old('national_id') }}">
                            @error('national_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                            @error('notes') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Register Member
                </button>
            </div>

        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const orgSelect = document.getElementById('organization_id');
    const branchSelect = document.getElementById('branch_id');
    const groupSelect = document.getElementById('vicoba_group_id');

    const allBranches = Array.from(branchSelect.options).filter(o => o.value);
    const allGroups = Array.from(groupSelect.options).filter(o => o.value);

    function filterBranches() {
        const orgId = orgSelect.value;
        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        allBranches.forEach(opt => {
            if (!orgId || opt.dataset.org === orgId) {
                branchSelect.appendChild(opt.cloneNode(true));
            }
        });
        groupSelect.innerHTML = '<option value="">Select Group</option>';
    }

    function filterGroups() {
        const branchId = branchSelect.value;
        groupSelect.innerHTML = '<option value="">Select Group</option>';
        allGroups.forEach(opt => {
            if (!branchId || opt.dataset.branch === branchId) {
                groupSelect.appendChild(opt.cloneNode(true));
            }
        });
    }

    orgSelect.addEventListener('change', filterBranches);
    branchSelect.addEventListener('change', filterGroups);

    filterBranches();
    filterGroups();
});
</script>
@endpush
