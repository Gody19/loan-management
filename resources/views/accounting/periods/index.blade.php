@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Accounting Periods</h4>
        <a href="{{ route('accounting.periods.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Period</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Journal Entries</th>
                            <th>Closed At</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                            <tr>
                                <td>{{ $period->name }}</td>
                                <td>{{ $period->start_date->format('d M Y') }}</td>
                                <td>{{ $period->end_date->format('d M Y') }}</td>
                                <td><span class="badge bg-{{ $period->status->color() }}">{{ $period->status->label() }}</span></td>
                                <td>{{ $period->journalEntries()->count() }}</td>
                                <td>{{ $period->closed_at ? $period->closed_at->format('d M Y H:i') : '-' }}</td>
                                <td>
                                    <a href="{{ route('accounting.periods.show', $period->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    @if($period->isOpen())
                                        <form action="{{ route('accounting.periods.close', $period->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Close this period? No more journal entries can be posted.')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-lock"></i> Close</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No accounting periods. Create one to start posting journals.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
