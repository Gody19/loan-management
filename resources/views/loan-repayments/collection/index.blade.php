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
        <div class="col-lg-10 col-xl-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Find Member</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Search for a member by name, phone number, or member number to record a loan repayment on their behalf.</p>

                    <form action="{{ route('loan-repayments-collection.index') }}" method="GET">
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" value="{{ $search ?? '' }}"
                                   placeholder="Search by name, phone, or member number..." autofocus>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>

                    @if($search && isset($members) && $members->isEmpty())
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            No members found matching "<strong>{{ $search }}</strong>". Try a different search term.
                        </div>
                    @endif

                    @if(isset($members) && $members->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member</th>
                                        <th>Member #</th>
                                        <th>Phone</th>
                                        <th>VICOBA Group</th>
                                        <th>Active Loans</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($members as $member)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $member->full_name }}</div>
                                            </td>
                                            <td><span class="badge bg-light text-dark">{{ $member->member_number }}</span></td>
                                            <td>{{ $member->phone ?? 'N/A' }}</td>
                                            <td>{{ $member->vicobaGroup?->name ?? 'N/A' }}</td>
                                            <td>
                                                @php($activeCount = $member->loans()->where('status', 'active')->count())
                                                @if($activeCount > 0)
                                                    <span class="badge bg-success">{{ $activeCount }} active</span>
                                                @else
                                                    <span class="badge bg-secondary">0</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('loan-repayments-collection.member-loans', $member) }}"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i> View Loans
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                    @endforelse
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
