@php
    /**
     * Recurring management report schedules (Phase 12.1).
     *
     * Rendered on the reporting dashboard. Every mutating control posts to a
     * capability-gated route (ai.reports.schedule); the server re-checks tenant
     * scope and capability on every action, so this view only ever lists
     * schedules the acting user may actually administer. A manual run shares the
     * scheduler's idempotent path and never modifies the schedule definition.
     */
    $weekdayLabels = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
@endphp

@if ($canSchedule)
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="vicoba-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar2-week me-1"></i> Scheduled reports</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleCreateModal">
                        <i class="bi bi-plus-lg me-1"></i> New schedule
                    </button>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Recurring reports reuse the same deterministic reporting service. Each run covers a
                        <strong>completed</strong> period — the previous day, week, month or quarter — resolved in the
                        schedule's own timezone, and each schedule can produce at most one report per period.
                    </p>

                    @if ($schedules->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-calendar2-plus d-block mb-2" style="font-size: 1.8rem;"></i>
                            No recurring reports are scheduled yet.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Schedule</th>
                                        <th>Frequency</th>
                                        <th>Recipients</th>
                                        <th>Next run</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($schedules as $schedule)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $schedule->name }}</div>
                                                <div class="small text-muted">
                                                    {{ $schedule->report_type->label() }}
                                                    &middot; {{ $schedule->branch?->name ?? 'All authorized branches' }}
                                                    &middot; {{ $schedule->timezone }}
                                                </div>
                                            </td>
                                            <td class="small">
                                                {{ $schedule->frequency->label() }}
                                                <div class="text-muted">
                                                    {{ $schedule->run_time ? substr($schedule->run_time, 0, 5) : '' }}
                                                    @if ($schedule->frequency->requiresWeekday() && $schedule->weekday)
                                                        &middot; {{ $weekdayLabels[$schedule->weekday] ?? '' }}
                                                    @endif
                                                    @if ($schedule->frequency->requiresDayOfMonth() && $schedule->day_of_month)
                                                        &middot; day {{ $schedule->day_of_month }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="small">
                                                {{ $schedule->recipient_mode->label() }}
                                                @if ($schedule->recipient_mode->requiresRecipients())
                                                    <div class="text-muted">{{ count((array) $schedule->recipients) }} selected</div>
                                                @endif
                                            </td>
                                            <td class="small">
                                                @if ($schedule->next_run_at)
                                                    {{ $schedule->next_run_at->format('Y-m-d H:i') }}
                                                @else
                                                    <span class="text-muted">Paused</span>
                                                @endif
                                                @if ($schedule->last_run_at)
                                                    <div class="text-muted">last {{ $schedule->last_run_at->diffForHumans() }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($schedule->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Disabled</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal" data-bs-target="#scheduleEditModal{{ $schedule->id }}">
                                                        Edit
                                                    </button>

                                                    <form method="POST" action="{{ route('ai.reports.schedules.run', $schedule) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Run now</button>
                                                    </form>

                                                    <form method="POST" action="{{ route('ai.reports.schedules.toggle', $schedule) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                            {{ $schedule->is_active ? 'Disable' : 'Enable' }}
                                                        </button>
                                                    </form>

                                                    <form method="POST" action="{{ route('ai.reports.schedules.destroy', $schedule) }}"
                                                          onsubmit="return confirm('Delete this schedule? Its produced reports are kept.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                </div>
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

    {{-- Create schedule --}}
    <div class="modal fade" id="scheduleCreateModal" tabindex="-1" aria-labelledby="scheduleCreateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('ai.reports.schedules.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleCreateModalLabel">New scheduled report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('ai.reports.partials.schedule-form', ['schedule' => null, 'prefix' => 'create'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit schedules --}}
    @foreach ($schedules as $schedule)
        <div class="modal fade" id="scheduleEditModal{{ $schedule->id }}" tabindex="-1" aria-labelledby="scheduleEditModalLabel{{ $schedule->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('ai.reports.schedules.update', $schedule) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title" id="scheduleEditModalLabel{{ $schedule->id }}">Edit schedule</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @include('ai.reports.partials.schedule-form', ['schedule' => $schedule, 'prefix' => 'edit'.$schedule->id])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <script>
        (function () {
            function sync(prefix) {
                var frequency = document.getElementById(prefix + '_frequency');
                if (!frequency) { return; }

                var weekday = document.getElementById(prefix + '_weekday_wrap');
                var day = document.getElementById(prefix + '_day_wrap');
                var mode = document.getElementById(prefix + '_recipient_mode');
                var recipients = document.getElementById(prefix + '_recipients_wrap');

                function toggleFrequency() {
                    var value = frequency.value;
                    if (weekday) { weekday.style.display = (value === 'weekly') ? '' : 'none'; }
                    if (day) { day.style.display = (value === 'monthly' || value === 'quarterly') ? '' : 'none'; }
                }

                function toggleRecipients() {
                    if (!mode || !recipients) { return; }
                    recipients.style.display = (mode.value === 'specific_users') ? '' : 'none';
                }

                frequency.addEventListener('change', toggleFrequency);
                if (mode) { mode.addEventListener('change', toggleRecipients); }
                toggleFrequency();
                toggleRecipients();
            }

            sync('create');
            @foreach ($schedules as $schedule)
                sync('edit{{ $schedule->id }}');
            @endforeach
        })();
    </script>
@endif