@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Period: {{ $period->name }}</h4>
        <div>
            @if($period->isOpen())
                <form action="{{ route('accounting.periods.close', $period->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Close this period?')">
                    @csrf
                    <button type="submit" class="btn btn-danger"><i class="fas fa-lock me-1"></i> Close Period</button>
                </form>
            @endif
            <a href="{{ route('accounting.periods.index') }}" class="btn btn-outline-secondary ms-2"><i class="fas fa-arrow-left me-1"></i> Back</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Status</h6>
                    <span class="badge bg-{{ $period->status->color() }} fs-6">{{ $period->status->label() }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Start Date</h6>
                    <h5 class="mb-0">{{ $period->start_date->format('d M Y') }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-1">End Date</h6>
                    <h5 class="mb-0">{{ $period->end_date->format('d M Y') }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Journal Entries</h6>
                    <h5 class="mb-0">{{ $period->journalEntries()->count() }}</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h6 class="mb-0">Journal Entries in Period</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Journal #</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Lines</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($journalEntries as $entry)
                            <tr>
                                <td><a href="{{ route('accounting.journals.show', $entry->id) }}">{{ $entry->journal_number }}</a></td>
                                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                                <td>{{ Str::limit($entry->description, 60) }}</td>
                                <td><span class="badge bg-{{ $entry->status->color() }}">{{ $entry->status->label() }}</span></td>
                                <td>{{ $entry->lines->count() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No journal entries in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $journalEntries->links() }}
        </div>
    </div>
</div>
@endsection
