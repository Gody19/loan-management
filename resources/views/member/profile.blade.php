@extends('layouts.member')

@section('title', 'My Profile - FinancePro VICOBA')
@section('page-title', 'My Profile')

@php
    $activeTab = request('tab', 'profile');
@endphp

@section('content')
<div class="row">
    <div class="col-12">

        {{-- Identity Header Card --}}
        <div class="card vicoba-card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 72px; height: 72px;">
                        <span class="text-white fw-bold" style="font-size: 1.75rem;">
                            {{ substr($member->full_name, 0, 1) }}
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="fw-bold mb-1">{{ $member->full_name }}</h4>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary">{{ $member->member_number }}</span>
                            <span class="badge bg-{{ $member->membership_status->color() }}">{{ $member->membership_status->label() }}</span>
                            @if($member->phone)
                                <span class="text-muted small"><i class="bi bi-telephone me-1"></i>{{ $member->phone }}</span>
                            @endif
                            @if($member->email)
                                <span class="text-muted small"><i class="bi bi-envelope me-1"></i>{{ $member->email }}</span>
                            @endif
                        </div>
                        <div class="text-muted small mt-1">
                            @if($member->organization)<i class="bi bi-building me-1"></i>{{ $member->organization->name }}@endif
                            @if($member->branch) &middot; {{ $member->branch->name }}@endif
                            @if($member->vicobaGroup) &middot; {{ $member->vicobaGroup->name }}@endif
                        </div>
                    </div>
                    <div class="text-end">
                        <a href="{{ route('member.profile.edit') }}" class="btn btn-primary">
                            <i class="bi bi-pencil me-1"></i> Edit Profile
                        </a>
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
                    <div class="col-md-6">
                        <div class="card vicoba-card h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Personal Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">First Name</td><td class="fw-medium">{{ $member->first_name }}</td></tr>
                                    <tr><td class="text-muted">Middle Name</td><td class="fw-medium">{{ $member->middle_name ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Last Name</td><td class="fw-medium">{{ $member->last_name }}</td></tr>
                                    <tr><td class="text-muted">Gender</td><td class="fw-medium">{{ $member->gender?->label() ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Date of Birth</td><td class="fw-medium">{{ $member->date_of_birth?->format('d M Y') ?? '—' }}{{ $member->age ? ' (Age: ' . $member->age . ')' : '' }}</td></tr>
                                    <tr><td class="text-muted">Marital Status</td><td class="fw-medium">{{ $member->marital_status?->label() ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">National ID</td><td class="fw-medium">{{ $member->national_id ?? '—' }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card vicoba-card h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-telephone me-2"></i>Contact Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">Phone</td><td class="fw-medium">{{ $member->phone }}</td></tr>
                                    <tr><td class="text-muted">Alternate Phone</td><td class="fw-medium">{{ $member->alternate_phone ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Email</td><td class="fw-medium">{{ $member->email ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Region</td><td class="fw-medium">{{ $member->region ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">District</td><td class="fw-medium">{{ $member->district ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Ward</td><td class="fw-medium">{{ $member->ward ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Street</td><td class="fw-medium">{{ $member->street ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Address</td><td class="fw-medium">{{ $member->address ?? '—' }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card vicoba-card h-100">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-briefcase me-2"></i>Employment / Business</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted" style="width:40%">Occupation</td><td class="fw-medium">{{ $member->occupation ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Employer / Business</td><td class="fw-medium">{{ $member->employer_or_business ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Joining Date</td><td class="fw-medium">{{ $member->joining_date?->format('d M Y') ?? '—' }}</td></tr>
                                    <tr><td class="text-muted">Membership Status</td><td><span class="badge bg-{{ $member->membership_status->color() }}">{{ $member->membership_status->label() }}</span></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Next of Kin Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'next-of-kin' ? 'show active' : '' }}" id="tab-next-of-kin">
                <div class="card vicoba-card">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Next of Kin</h6>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNextOfKinModal">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </button>
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
                                            <th class="text-end">Actions</th>
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
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button"
                                                                class="btn btn-outline-secondary"
                                                                title="Edit"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editNextOfKinModal"
                                                                data-action="{{ route('member.next-of-kin.update', $kin) }}"
                                                                data-full-name="{{ $kin->full_name }}"
                                                                data-relationship="{{ $kin->relationship->value }}"
                                                                data-phone="{{ $kin->phone }}"
                                                                data-alternate-phone="{{ $kin->alternate_phone }}"
                                                                data-address="{{ $kin->address }}"
                                                                data-is-primary="{{ $kin->is_primary ? '1' : '0' }}">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="POST" action="{{ route('member.next-of-kin.destroy', $kin) }}" class="d-inline" data-confirm="Remove this next of kin?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No next of kin records yet. Click "Add" to add one.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Documents Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'documents' ? 'show active' : '' }}" id="tab-documents">
                <div class="card vicoba-card">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark me-2"></i>Documents</h6>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                            <i class="bi bi-upload me-1"></i> Upload
                        </button>
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
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('member.documents.download', $doc) }}" class="btn btn-outline-primary" title="Download">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        <form method="POST" action="{{ route('member.documents.destroy', $doc) }}" class="d-inline" data-confirm="Delete this document?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
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
                                No documents uploaded yet. Click "Upload" to add one.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Status History Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'status-history' ? 'show active' : '' }}" id="tab-status-history">
                <div class="card vicoba-card">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Status History</h6>
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

            {{-- Financial Summary Tab --}}
            <div class="tab-pane fade {{ $activeTab === 'financial' ? 'show active' : '' }}" id="tab-financial">
                <div class="row g-4">
                    {{-- Savings --}}
                    <div class="col-md-6">
                        <div class="card vicoba-card h-100">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-2 text-success"></i>Savings</h6>
                                <a href="{{ route('member.savings') }}" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Total Balance</span>
                                    <span class="fw-bold fs-5 text-success">TSh {{ number_format($savingsSummary['total_balance'], 2) }}</span>
                                </div>
                                @if($savingsSummary['accounts']->count())
                                    @foreach($savingsSummary['accounts'] as $account)
                                        <div class="d-flex justify-content-between small py-1 border-top">
                                            <span class="text-muted">{{ $account->account_number }} ({{ $account->product->name ?? '—' }})</span>
                                            <span class="fw-medium">TSh {{ number_format($account->current_balance, 2) }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-muted small text-center py-2">No savings accounts</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Shares --}}
                    <div class="col-md-6">
                        <div class="card vicoba-card h-100">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2 text-warning"></i>Shares</h6>
                                <a href="{{ route('member.shares') }}" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Shares</span>
                                    <span class="fw-bold">{{ number_format($sharesSummary['total_shares']) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Total Value</span>
                                    <span class="fw-bold fs-5 text-warning">TSh {{ number_format($sharesSummary['total_value'], 2) }}</span>
                                </div>
                                @if($sharesSummary['accounts']->count())
                                    @foreach($sharesSummary['accounts'] as $account)
                                        <div class="d-flex justify-content-between small py-1 border-top">
                                            <span class="text-muted">{{ $account->account_number }} ({{ $account->product->name ?? '—' }})</span>
                                            <span class="fw-medium">{{ number_format($account->total_shares) }} shares</span>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-muted small text-center py-2">No share accounts</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Welfare --}}
                    <div class="col-md-6">
                        <div class="card vicoba-card h-100">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-heart me-2 text-info"></i>Welfare</h6>
                                <a href="{{ route('member.welfare') }}" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Total Balance</span>
                                    <span class="fw-bold fs-5 text-info">TSh {{ number_format($welfareSummary['total_balance'], 2) }}</span>
                                </div>
                                @if($welfareSummary['accounts']->count())
                                    @foreach($welfareSummary['accounts'] as $account)
                                        <div class="d-flex justify-content-between small py-1 border-top">
                                            <span class="text-muted">{{ $account->account_number }} ({{ $account->fund->name ?? '—' }})</span>
                                            <span class="fw-medium">TSh {{ number_format($account->current_balance, 2) }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-muted small text-center py-2">No welfare accounts</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Loans --}}
                    <div class="col-md-6">
                        <div class="card vicoba-card h-100">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>Loans</h6>
                                <a href="{{ route('member.loans') }}" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Active Loans</span>
                                    <span class="fw-bold">{{ $loanSummary['active_count'] }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Completed Loans</span>
                                    <span class="fw-bold">{{ $loanSummary['completed_count'] }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Paid</span>
                                    <span class="fw-medium text-success">TSh {{ number_format($loanSummary['total_paid'], 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Outstanding Balance</span>
                                    <span class="fw-bold text-danger">TSh {{ number_format($loanSummary['total_outstanding'], 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Quick Links --}}
                    <div class="col-12">
                        <div class="card vicoba-card">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0 fw-semibold"><i class="bi bi-lightning me-2"></i>More</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('member.statements') }}" class="btn btn-outline-primary">
                                        <i class="bi bi-file-earmark-text me-1"></i> Full Financial Statement
                                    </a>
                                    <a href="{{ route('member.transactions') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left-right me-1"></i> My Transactions
                                    </a>
                                    <a href="{{ route('member.notifications') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-bell me-1"></i> Notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Add Next of Kin Modal --}}
<div class="modal fade" id="addNextOfKinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.next-of-kin.store') }}">
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
                        <label class="form-label">Alternate Phone</label>
                        <input type="text" name="alternate_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1">
                            <label class="form-check-label">Primary next of kin</label>
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

{{-- Edit Next of Kin Modal --}}
<div class="modal fade" id="editNextOfKinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editNextOfKinForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Next of Kin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="edit_kin_full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Relationship <span class="text-danger">*</span></label>
                        <select name="relationship" id="edit_kin_relationship" class="form-select" required>
                            <option value="">Select</option>
                            @foreach(\App\Enums\Relationship::cases() as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" id="edit_kin_phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alternate Phone</label>
                        <input type="text" name="alternate_phone" id="edit_kin_alternate_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_kin_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="edit_kin_is_primary">
                            <label class="form-check-label" for="edit_kin_is_primary">Primary next of kin</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Next of Kin</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Upload Document Modal --}}
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('member.documents.store') }}" enctype="multipart/form-data">
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
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = document.getElementById('editNextOfKinModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                document.getElementById('editNextOfKinForm').action = button.dataset.action;
                document.getElementById('edit_kin_full_name').value = button.dataset.fullName || '';
                document.getElementById('edit_kin_relationship').value = button.dataset.relationship || '';
                document.getElementById('edit_kin_phone').value = button.dataset.phone || '';
                document.getElementById('edit_kin_alternate_phone').value = button.dataset.alternatePhone || '';
                document.getElementById('edit_kin_address').value = button.dataset.address || '';
                document.getElementById('edit_kin_is_primary').checked = button.dataset.isPrimary === '1';
            });
            editModal.addEventListener('hidden.bs.modal', function() {
                document.getElementById('editNextOfKinForm').reset();
            });
        }

        document.querySelectorAll('form[data-confirm]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var message = form.dataset.confirm || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    });
</script>
@endpush