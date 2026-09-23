@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Journal Entries</h4>
        <a href="{{ route('accounting.journals.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Journal Entry</a>
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
                            <th>Journal #</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td><a href="{{ route('accounting.journals.show', $entry->id) }}">{{ $entry->journal_number }}</a></td>
                                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                                <td>{{ Str::limit($entry->description, 50) }}</td>
                                <td><span class="badge bg-{{ $entry->status->color() }}">{{ $entry->status->label() }}</span></td>
                                <td class="text-end">TSh {{ number_format($entry->lines->sum('debit'), 2) }}</td>
                                <td class="text-end">TSh {{ number_format($entry->lines->sum('credit'), 2) }}</td>
                                <td>
                                    <a href="{{ route('accounting.journals.show', $entry->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    @if($entry->status->value === 'draft')
                                        <form action="{{ route('accounting.journals.post', $entry->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Post this journal entry?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Post</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No journal entries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $entries->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
