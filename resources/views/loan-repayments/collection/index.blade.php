@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Record Loan Payment</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Record Payment</li>
                </ol>
            </nav>
        </div>
    </div>

    @include('layouts.components.alerts')

    <div class="row justify-content-center">
        <div class="col-lg-11 col-xl-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Members with Active Loans</h5>
                    <span class="badge bg-primary">{{ $members->count() }} member(s)</span>
                </div>
                <div class="card-body">
                    {{-- Search Form --}}
                    <form action="{{ route('loan-repayments-collection.index') }}" method="GET" class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" value="{{ $search ?? '' }}"
                                   placeholder="Filter by name, phone, or member number...">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            @if($search)
                                <a href="{{ route('loan-repayments-collection.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i> Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    @if($members->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            @if($search)
                                <p>No members found matching "<strong>{{ $search }}</strong>" with active unpaid loans.</p>
                            @else
                                <p>No members with active unpaid loans found.</p>
                            @endif
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member</th>
                                        <th>Member #</th>
                                        <th>Phone</th>
                                        <th>VICOBA Group</th>
                                        <th>Active Loans</th>
                                        <th class="text-end">Total Outstanding</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($members as $member)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $member->full_name }}</div>
                                            </td>
                                            <td><span class="badge bg-light text-dark">{{ $member->member_number }}</span></td>
                                            <td>{{ $member->phone ?? 'N/A' }}</td>
                                            <td>{{ $member->vicobaGroup?->name ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge bg-success">{{ $member->loans->count() }} active</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-danger fw-semibold">
                                                    TSh {{ number_format($member->loans->sum('outstanding_balance'), 2) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('loan-repayments-collection.member-loans', $member) }}"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-cash me-1"></i> Record Payment
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
