@extends('layouts.app')

@section('title', 'Member Profile')

@php
    $activeTab = session('tab', 'profile');
@endphp

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $member->full_name,
        'subtitle' => 'Member No: ' . $member->member_number . ' | ' . $member->membership_status->label(),
        'actions' => '<a href="' . route('members.index') . '" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        @can("update", $member)
        <a href="' . route('members.edit', $member) . '" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        @endcan'
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Profile Summary Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                        <span class="text-white fw-bold fs-4">{{ substr($member->first_name, 0, 1) }}{{ substr($member->last_name, 0, 1) }}</span>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 fw-bold">{{ $member->full_name }}</h4>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge bg-primary">{{ $member->member_number }}</span>
                            <span class="badge bg-{{ $member->membership_status->color() }}">{{ $member->membership_status->label() }}</span>
                            @if($member->phone)
                                <span class="text-muted"><i class="bi bi-telephone me-1"></i>{{ $member->phone }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Organization</div>
                        <div class="fw-medium">{{ $member->organization->name ?? '—' }}</div>
                        <div class="text-muted small mt-1">Branch</div>
                        <div class="fw-medium">{{ $member->branch->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'profile' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-profile">
                    <i class="bi bi-person me-1"></i> Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'next-of-kin' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-next-of-kin">
                    <i class="bi bi-people me-1"></i> Next of Kin
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-documents">
                    <i class="bi bi-file-earmark me-1"></i> Documents
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'status-history' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-status-history">
                    <i class="bi bi-clock-history me-1"></i> Status History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'financial' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-financial">
                    <i class="bi bi-wallet2 me-1"></i> Financial Summary
                </a>
            </li>
        </ul>

        <div class="tab-content">

            {{-- Profile Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="tab-profile">
                <div class="row g-4">
                    {{-- Contact Information --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-telephone me-2"></i>Contact Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">Phone</td><td class="fw-medium">{{ $member->phone }}</td></tr>
                                    <tr><td class="text-muted">Alternate Phone</td><td class="fw-medium">{{ $member->alternate_phone ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Email</td><td class="fw-medium">{{ $member->email ?? '—' }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Address --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-geo-alt me-2"></i>Address</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">Region</td><td class="fw-medium">{{ $member->region ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">District</td><td class="fw-medium">{{ $member->district ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Ward</td><td class="fw-medium">{{ $member->ward ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Street</td><td class="fw-medium">{{ $member->street ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Address</td><td class="fw-medium">{{ $member->address ?? '—' }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Personal Information --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Personal Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">Gender</td><td class="fw-medium">{{ $member->gender?->label() ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Date of Birth</td><td class="fw-medium">{{ $member->date_of_birth?->format('d M Y') ?? '—' }}{{ $member->age ? ' (Age: ' . $member->age . ')' : '' }}</td></tr>
                                    <tr><td class="text-muted">Marital Status</td><td class="fw-medium">{{ $member->marital_status?->label() ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">National ID</td><td class="fw-medium">{{ $member->national_id ?? '—' }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Employment --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-briefcase me-2"></i>Employment / Business</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">Occupation</td><td class="fw-medium">{{ $member->occupation ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Employer/Business</td><td class="fw-medium">{{ $member->employer_or_business ?? '—' }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Membership --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Membership</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">Organization</td><td class="fw-medium">{{ $member->organization->name ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Branch</td><td class="fw-medium">{{ $member->branch->name ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Group</td><td class="fw-medium">{{ $member->vicobaGroup->name ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Joining Date</td><td class="fw-medium">{{ $member->joining_date?->format('d M Y') ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $member->membership_status->color() }}">{{ $member->membership_status->label() }}</span></td></tr>
                                    @if($member->notes)
                                    <tr><td class="text-muted">Notes</td><td class="fw-medium">{{ $member->notes }}</td></tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Next of Kin Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'next-of-kin' ? 'show active' : '' }}" id="tab-next-of-kin">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Next of Kin</h6>
                        @can('manageNextOfKin', $member)
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNextOfKinModal">
                                <i class="bi bi-plus-lg me-1"></i> Add
                            </button>
                        @endcan
                    </div>
                    <div class="card-body">
                        @if($member->nextOfKins->count())
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Relationship</th>
                                            <th>Phone</th>
                                            <th>Address</th>
                                            <th>Primary</th>
                                            @can('manageNextOfKin', $member)
                                                <th class="text-end">Actions</th>
                                            @endcan
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($member->nextOfKins as $kin)
                                            <tr>
                                                <td class="fw-medium">{{ $kin->full_name }}</td>
                                                <td>{{ $kin->relationship->label() }}</td>
                                                <td>{{ $kin->phone }}</td>
                                                <td>{{ $kin->address ?? '—' }}</td>
                                                <td>
                                                    @if($kin->is_primary)
                                                        <span class="badge bg-success">Primary</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                @can('manageNextOfKin', $member)
                                                    <td class="text-end">
                                                        <form method="POST" action="{{ route('members.next-of-kin.destroy', [$member, $kin]) }}" class="d-inline" onsubmit="return confirm('Remove this next of kin?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    </td>
                                                @endcan
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No next of kin records.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Documents Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'documents' ? 'show active' : '' }}" id="tab-documents">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Documents</h6>
                        @can('manageDocuments', $member)
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                <i class="bi bi-upload me-1"></i> Upload
                            </button>
                        @endcan
                    </div>
                    <div class="card-body">
                        @if($member->documents->count())
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Document No.</th>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Verification</th>
                                            <th>Verified By</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($member->documents as $doc)
                                            <tr>
                                                <td><span class="badge bg-light text-dark">{{ $doc->document_type->label() }}</span></td>
                                                <td>{{ $doc->document_number ?? '—' }}</td>
                                                <td>{{ $doc->original_filename }}</td>
                                                <td>{{ number_format($doc->file_size / 1024, 1) }} KB</td>
                                                <td>
                                                    <span class="badge bg-{{ $doc->verification_status->color() }}">
                                                        {{ $doc->verification_status->label() }}
                                                    </span>
                                                </td>
                                                <td>{{ $doc->verifier->fullname ?? '—' }}</td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('members.documents.download', [$member, $doc]) }}" class="btn btn-outline-primary" title="Download">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        @can('manageDocuments', $member)
                                                            @if($doc->verification_status->value === 'pending')
                                                                <form method="POST" action="{{ route('members.documents.verify', [$member, $doc]) }}" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="verification_status" value="verified">
                                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Verify"><i class="bi bi-check-lg"></i></button>
                                                                </form>
                                                                <form method="POST" action="{{ route('members.documents.verify', [$member, $doc]) }}" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="verification_status" value="rejected">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                                                </form>
                                                            @endif
                                                            <form method="POST" action="{{ route('members.documents.destroy', [$member, $doc]) }}" class="d-inline" onsubmit="return confirm('Delete this document?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                                            </form>
                                                        @endcan
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-file-earmark fs-1 d-block mb-2"></i>
                                No documents uploaded.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Status History Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'status-history' ? 'show active' : '' }}" id="tab-status-history">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold">Status History</h6>
                    </div>
                    <div class="card-body">
                        @if($member->statusHistories->count())
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Reason</th>
                                            <th>Changed By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($member->statusHistories->sortByDesc('changed_at') as $history)
                                            <tr>
                                                <td>{{ $history->changed_at?->format('d M Y H:i') ?? '—' }}</td>
                                                <td><span class="badge bg-{{ $history->old_status->color() }}">{{ $history->old_status->label() }}</span></td>
                                                <td><span class="badge bg-{{ $history->new_status->color() }}">{{ $history->new_status->label() }}</span></td>
                                                <td>{{ $history->reason ?? '—' }}</td>
                                                <td>{{ $history->changer->fullname ?? 'System' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                                No status changes recorded.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Financial Summary Tab (Placeholder) --}}
            <div class="tab-pane fade {{ $activeTab === 'financial' ? 'show active' : '' }}" id="tab-financial">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-wallet2 text-muted fs-1"></i>
                        </div>
                        <h5 class="text-muted">Financial Summary</h5>
                        <p class="text-muted mb-0">Savings, shares, loans, and financial records will be available once those modules are implemented.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Add Next of Kin Modal --}}
@can('manageNextOfKin', $member)
<div class="modal fade" id="addNextOfKinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('members.next-of-kin.store', $member) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Next of Kin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Relationship <span class="text-danger">*</span></label>
                        <select name="relationship" class="form-select" required>
                            <option value="">Select</option>
                            @foreach(\App\Enums\Relationship::cases() as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="isPrimary">
                            <label class="form-check-label" for="isPrimary">Primary next of kin</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Next of Kin</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

{{-- Upload Document Modal --}}
@can('manageDocuments', $member)
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('members.documents.store', $member) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-select" required>
                            <option value="">Select Type</option>
                            @foreach(\App\Enums\DocumentType::cases() as $dt)
                                <option value="{{ $dt->value }}">{{ $dt->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document Number</label>
                        <input type="text" name="document_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Max 5MB. PDF, JPG, JPEG, PNG only.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
