@extends('layouts.app')

@section('title', 'Open Share Account')

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Open Share Account',
        'subtitle' => 'Create a new share account for a member',
        'actions' => '<a href="' . route('share-accounts.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>'
    ])
@endsection

@section('content')
<form method="POST" action="{{ route('share-accounts.store') }}" data-validate>
    @csrf

    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2"></i>Account Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Member <span class="text-danger">*</span></label>
                            <select name="member_id" class="form-select" required>
                                <option value="">Select Member</option>
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>{{ $member->full_name }} ({{ $member->member_number }})</option>
                                @endforeach
                            </select>
                            @error('member_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Share Plan <span class="text-danger">*</span></label>
                            <select name="share_product_id" class="form-select" required>
                                <option value="">Select Plan</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" {{ old('share_product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ number_format($product->share_price, 2) }}/share)</option>
                                @endforeach
                            </select>
                            @error('share_product_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

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

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('share-accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
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
