@extends('layouts.app')

@section('title', 'Open Savings Account')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Open Savings Account',
        'subtitle' => 'Register a new savings account for a member',
        'breadcrumb' => [
            ['label' => 'Member Savings Accounts', 'url' => route('savings-accounts.index')],
            ['label' => 'Open Account'],
        ],
        'actions' => '<a href="' . route('savings-accounts.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('savings-accounts.store') }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-8">

            {{-- Account Details --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2"></i>Account Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Member <span class="text-danger">*</span></label>
                            <select name="member_id" id="member_id" class="form-select" required>
                                <option value="">Select Member</option>
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}" data-org="{{ $member->organization_id }}" data-branch="{{ $member->branch_id }}" data-group="{{ $member->vicoba_group_id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>{{ $member->full_name }} ({{ $member->member_number }})</option>
                                @endforeach
                            </select>
                            @error('member_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Savings Plan <span class="text-danger">*</span></label>
                            <select name="savings_product_id" id="savings_product_id" class="form-select" required>
                                <option value="">Select Plan</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-org="{{ $product->organization_id }}" {{ old('savings_product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->code }})</option>
                                @endforeach
                            </select>
                            @error('savings_product_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Organization & Branch --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Organization & Branch</h6>
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
                            <label class="form-label">Group</label>
                            <select name="vicoba_group_id" id="vicoba_group_id" class="form-select">
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

            {{-- Opening Details --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-check me-2"></i>Opening Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Opening Date <span class="text-danger">*</span></label>
                            <input type="date" name="opening_date" class="form-control" value="{{ old('opening_date', date('Y-m-d')) }}" required>
                            @error('opening_date') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('savings-accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Open Account
                </button>
            </div>

        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const memberSelect = document.getElementById('member_id');
    const productSelect = document.getElementById('savings_product_id');
    const orgSelect = document.getElementById('organization_id');
    const branchSelect = document.getElementById('branch_id');
    const groupSelect = document.getElementById('vicoba_group_id');

    const allBranches = Array.from(branchSelect.options).filter(o => o.value);
    const allGroups = Array.from(groupSelect.options).filter(o => o.value);

    memberSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            const orgId = selected.dataset.org;
            const branchId = selected.dataset.branch;
            const groupId = selected.dataset.group;

            if (orgId) orgSelect.value = orgId;
            filterBranches();
            if (branchId) branchSelect.value = branchId;
            filterGroups();
            if (groupId) groupSelect.value = groupId;
        }
    });

    productSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value && selected.dataset.org) {
            orgSelect.value = selected.dataset.org;
            filterBranches();
        }
    });

    orgSelect.addEventListener('change', filterBranches);
    branchSelect.addEventListener('change', filterGroups);

    function filterBranches() {
        const orgId = orgSelect.value;
        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        allBranches.forEach(opt => {
            if (!orgId || opt.dataset.org === orgId) {
                branchSelect.appendChild(opt.cloneNode(true));
            }
        });
        filterGroups();
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
});
</script>
@endpush
