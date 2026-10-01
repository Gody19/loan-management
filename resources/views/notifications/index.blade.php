@extends('layouts.app')

@section('title', 'Notifications - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Notifications',
        'subtitle' => 'Your personal in-app feed — proactive AI insights and alerts from your authorized organizations.',
    ])
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12">
            <div class="vicoba-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-bell me-1"></i> Notification inbox</span>
                    @if ($notifications->total() > 0)
                        <form action="{{ route('notifications.read-all') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">Mark all read</button>
                        </form>
                    @endif
                </div>
                <div class="card-body p-0">
                    @forelse($notifications as $notification)
                        @php
                            $data = $notification->data;
                            $severity = data_get($data, 'severity');
                            $severityLabel = data_get($data, 'severity_label');
                            $url = data_get($data, 'url', '#');
                            $badgeClass = match ($severity) {
                                'critical' => 'danger',
                                'warning' => 'warning',
                                'notice' => 'info',
                                default => 'secondary',
                            };
                        @endphp
                        <div class="d-flex align-items-start gap-3 p-3 {{ ! $loop->last ? 'border-bottom' : '' }} {{ $notification->read_at ? '' : 'bg-light' }}">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold small">{{ data_get($data, 'title', 'Notification') }}</span>
                                    @if ($severity)
                                        <span class="badge bg-{{ $badgeClass }}">{{ $severityLabel }}</span>
                                    @endif
                                    @if (! $notification->read_at)
                                        <span class="badge bg-primary">New</span>
                                    @endif
                                </div>
                                <div class="small text-muted mt-1">{{ data_get($data, 'summary', '') }}</div>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-1 align-items-end">
                                @if ($url !== '#')
                                    <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary">Open</a>
                                @endif
                                @if (! $notification->read_at)
                                    <form action="{{ route('notifications.read', $notification) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Mark read</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-bell d-block mb-2" style="font-size: 2rem;"></i>
                            No notifications yet.
                        </div>
                    @endforelse
                </div>
                @if ($notifications->hasPages())
                    <div class="card-footer">
                        {{ $notifications->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection