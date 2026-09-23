@extends('layouts.app')

@section('content')
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Journal Entry: {{ $entry->journal_number }}</h4>
                <div>
                    @if($entry->status->value === 'draft')
                        <form action="{{ route('accounting.journals.post', $entry->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Post this journal entry? This action cannot be undone.')">
                            @csrf
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Post</button>
                        </form>
                    @endif
                    @if($entry->status->value === 'posted')
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#reversalModal"><i class="bi bi-arrow-counterclockwise me-1"></i> Reverse</button>
                    @endif
                    <a href="{{ route('accounting.journals.index') }}" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Status</h6>
                            <span class="badge bg-{{ $entry->status->color() }} fs-6">{{ $entry->status->label() }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Entry Date</h6>
                            <h5 class="mb-0">{{ $entry->entry_date->format('d M Y') }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Total Debit</h6>
                            <h5 class="mb-0 text-primary">TSh {{ number_format($entry->total_debit, 2) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Total Credit</h6>
                            <h5 class="mb-0 text-primary">TSh {{ number_format($entry->total_credit, 2) }}</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Entry Details</h6></div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><th width="150">Journal Number</th><td>{{ $entry->journal_number }}</td></tr>
                        <tr><th>Description</th><td>{{ $entry->description }}</td></tr>
                        <tr><th>Period</th><td>{{ $entry->accountingPeriod->name }}</td></tr>
                        @if($entry->branch)<tr><th>Branch</th><td>{{ $entry->branch->name }}</td></tr>@endif
                        @if($entry->posted_at)<tr><th>Posted At</th><td>{{ $entry->posted_at->format('d M Y H:i') }}</td></tr>@endif
                        @if($entry->reversal_reason)<tr><th>Reversal Reason</th><td>{{ $entry->reversal_reason }}</td></tr>@endif
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header"><h6 class="mb-0">Journal Lines</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit (TSh)</th>
                                    <th class="text-end">Credit (TSh)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entry->lines as $line)
                                    <tr>
                                        <td><code>{{ $line->account->account_code }}</code> {{ $line->account->account_name }}</td>
                                        <td>{{ $line->description ?? '-' }}</td>
                                        <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                                        <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2">Total</th>
                                    <th class="text-end">TSh {{ number_format($entry->total_debit, 2) }}</th>
                                    <th class="text-end">TSh {{ number_format($entry->total_credit, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

<!-- Reversal Modal -->
@if($entry->status->value === 'posted')
<div class="modal fade" id="reversalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('accounting.journals.reverse', $entry->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reverse Journal Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>This will create a reversal journal for <strong>{{ $entry->journal_number }}</strong>. The original will remain in history.</p>
                    <div class="mb-3">
                        <label for="reversal_reason" class="form-label">Reason for Reversal *</label>
                        <textarea class="form-control" name="reversal_reason" id="reversal_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Reversal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
