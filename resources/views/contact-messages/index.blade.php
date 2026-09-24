@extends('layouts.app')

@section('title', 'Contact Messages - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Contact Messages',
        'subtitle' => 'Messages submitted through the landing page contact form',
        'breadcrumb' => [
            ['label' => 'Contact Messages'],
        ],
    ])
@endsection

@section('content')

<div class="row g-3 mb-4">
    @php
        $unreadCount = \App\Models\ContactMessage::query()->unread()->count();
        $totalCount = \App\Models\ContactMessage::count();
    @endphp
    <div class="col-md-6 col-lg-3">
        <div class="card vicoba-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-envelope-exclamation text-warning fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Unread</div>
                    <div class="fw-bold fs-4" id="unreadCount">{{ $unreadCount }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card vicoba-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-envelope text-primary fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Messages</div>
                    <div class="fw-bold fs-4">{{ $totalCount }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($messages->isEmpty())
    <div class="card vicoba-card">
        <div class="card-body text-center py-5">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-envelope-open text-muted" style="font-size: 2rem;"></i>
            </div>
            <h5 class="text-muted">No Messages Yet</h5>
            <p class="text-muted mb-0">Messages submitted through the contact form on the landing page will appear here.</p>
        </div>
    </div>
@else
    <div class="card vicoba-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Message</th>
                            <th>Sender</th>
                            <th>Subject</th>
                            <th>Received</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $message)
                            <tr class="{{ $message->is_read ? '' : 'table-info' }}">
                                <td>
                                    <div class="text-truncate" style="max-width: 320px;" title="{{ $message->message }}">
                                        {{ $message->message }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $message->name }}</div>
                                    <div class="small text-muted">{{ $message->email }}</div>
                                </td>
                                <td>
                                    @if($message->subject)
                                        <span class="badge bg-light text-dark">{{ $message->subject }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">{{ $message->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($message->is_read)
                                        <span class="badge bg-success">Read</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Unread</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button"
                                                class="btn btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewMessageModal"
                                                data-id="{{ $message->id }}"
                                                data-read="{{ $message->is_read ? '1' : '0' }}"
                                                data-name="{{ $message->name }}"
                                                data-email="{{ $message->email }}"
                                                data-phone="{{ $message->phone }}"
                                                data-subject="{{ $message->subject }}"
                                                data-message="{{ $message->message }}"
                                                data-date="{{ $message->created_at->format('d M Y H:i') }}"
                                                title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <form method="POST" action="{{ route('contact-messages.destroy', $message) }}" class="d-inline" data-confirm="Delete this contact message?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $messages->links() }}
    </div>
@endif

{{-- View Message Modal --}}
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contact Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-borderless table-sm mb-3">
                    <tr><td class="text-muted" style="width: 35%;">Name</td><td class="fw-medium" id="view_name">—</td></tr>
                    <tr><td class="text-muted">Email</td><td class="fw-medium" id="view_email">—</td></tr>
                    <tr><td class="text-muted">Phone</td><td class="fw-medium" id="view_phone">—</td></tr>
                    <tr><td class="text-muted">Subject</td><td class="fw-medium" id="view_subject">—</td></tr>
                    <tr><td class="text-muted">Received</td><td class="fw-medium" id="view_date">—</td></tr>
                </table>
                <div class="p-3 bg-light rounded-3">
                    <div class="fw-semibold small mb-1">Message</div>
                    <div id="view_message" class="text-muted" style="white-space: pre-wrap;">—</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('viewMessageModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                document.getElementById('view_name').textContent = button.dataset.name || '—';
                document.getElementById('view_email').textContent = button.dataset.email || '—';
                document.getElementById('view_phone').textContent = button.dataset.phone || '—';
                document.getElementById('view_subject').textContent = button.dataset.subject || '—';
                document.getElementById('view_date').textContent = button.dataset.date || '—';
                document.getElementById('view_message').textContent = button.dataset.message || '—';

                if (button.dataset.read === '0' && button.dataset.id) {
                    button.dataset.read = '1';
                    markAsRead(button.dataset.id, button.closest('tr'));
                }
            });
        }

        function markAsRead(id, row) {
            fetch('{{ route('contact-messages.read', ':id') }}'.replace(':id', id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: '_method=PATCH'
            }).then(function(response) {
                if (!response.ok) {
                    return;
                }
                var unreadEl = document.getElementById('unreadCount');
                if (unreadEl) {
                    var n = parseInt(unreadEl.textContent, 10) || 0;
                    unreadEl.textContent = Math.max(0, n - 1);
                }
                if (row) {
                    row.classList.remove('table-info');
                    var badge = row.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge bg-success';
                        badge.textContent = 'Read';
                    }
                }
            });
        }
    });
</script>
@endpush