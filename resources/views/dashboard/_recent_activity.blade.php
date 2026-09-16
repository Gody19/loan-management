<div class="card vicoba-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">Recent Activity</h6>
    </div>
    <div class="card-body p-0">
        @if($recent_activity && $recent_activity->count())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Transaction</th>
                            @if(isset($activity['member']) || ($recent_activity->first() && isset($recent_activity->first()['member'])))
                            <th>Member</th>
                            @endif
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent_activity as $activity)
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $activity['color'] }}-subtle text-{{ $activity['color'] }}">
                                        <i class="bi {{ $activity['icon'] }} me-1"></i>
                                        {{ $activity['label'] }}
                                    </span>
                                </td>
                                @if(isset($activity['member']))
                                <td>{{ $activity['member'] }}</td>
                                @endif
                                <td>TSh {{ number_format($activity['amount'], 2) }}</td>
                                <td>{{ $activity['date']?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                <p class="mb-0" style="font-size: 0.875rem;">No recent activity to display.</p>
                <small>Activity will appear here as members interact with the system.</small>
            </div>
        @endif
    </div>
</div>
