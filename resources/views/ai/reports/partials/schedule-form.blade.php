@php
    /**
     * Shared create/edit form for a recurring report schedule.
     *
     * `$schedule` is null for the create form and an existing model for an edit
     * form; `$prefix` makes every control id unique so the create and edit forms
     * can live on the same page. The frequency controls are shown/hidden by the
     * inline script in the parent partial.
     */
    $scheduleTimezone = old('timezone', $schedule?->timezone ?? config('app.timezone'));
    $selectedRecipients = old('recipients', $schedule ? (array) $schedule->recipients : []);
@endphp

<div class="mb-3">
    <label for="{{ $prefix }}_name" class="form-label">Schedule name</label>
    <input type="text" name="name" id="{{ $prefix }}_name" maxlength="120" required
           value="{{ old('name', $schedule?->name) }}"
           class="form-control @error('name') is-invalid @enderror"
           placeholder="e.g. Monthly executive pack">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-2 mb-3">
    <div class="col-md-6">
        <label for="{{ $prefix }}_report_type" class="form-label">Report type</label>
        <select name="report_type" id="{{ $prefix }}_report_type" class="form-select" required>
            @foreach ($reportTypes as $type)
                <option value="{{ $type->value }}" @selected(old('report_type', $schedule?->report_type?->value) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Accounting reporting additionally requires the accounting capability.</div>
    </div>

    <div class="col-md-6">
        <label for="{{ $prefix }}_frequency" class="form-label">Frequency</label>
        <select name="frequency" id="{{ $prefix }}_frequency" class="form-select" required>
            @foreach ($scheduleFrequencies as $frequency)
                <option value="{{ $frequency->value }}" @selected(old('frequency', $schedule?->frequency?->value) === $frequency->value)>
                    {{ $frequency->label() }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Each run covers the previous completed period.</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-4">
        <label for="{{ $prefix }}_run_time" class="form-label">Run time</label>
        <input type="time" name="run_time" id="{{ $prefix }}_run_time" required
               value="{{ old('run_time', $schedule?->run_time ? substr($schedule->run_time, 0, 5) : '06:00') }}"
               class="form-control">
    </div>

    <div class="col-md-4" id="{{ $prefix }}_weekday_wrap">
        <label for="{{ $prefix }}_weekday" class="form-label">Weekday <span class="text-muted small">(weekly)</span></label>
        <select name="weekday" id="{{ $prefix }}_weekday" class="form-select">
            @foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $value => $label)
                <option value="{{ $value }}" @selected((int) old('weekday', $schedule?->weekday ?? 1) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4" id="{{ $prefix }}_day_wrap">
        <label for="{{ $prefix }}_day_of_month" class="form-label">Day of month <span class="text-muted small">(monthly/quarterly)</span></label>
        <input type="number" name="day_of_month" id="{{ $prefix }}_day_of_month" min="1" max="31"
               value="{{ old('day_of_month', $schedule?->day_of_month ?? 1) }}" class="form-control">
        <div class="form-text">Clamped to the month's last day when shorter.</div>
    </div>

    <div class="col-md-4">
        <label for="{{ $prefix }}_timezone" class="form-label">Timezone</label>
        <input type="text" name="timezone" id="{{ $prefix }}_timezone"
               value="{{ $scheduleTimezone }}" class="form-control"
               placeholder="{{ config('app.timezone') }}">
        <div class="form-text">An IANA timezone identifier. The reporting day is resolved here.</div>
    </div>
</div>

@if (count($branches) > 0)
    <div class="mb-3">
        <label for="{{ $prefix }}_branch_id" class="form-label">Branch <span class="text-muted small">(optional)</span></label>
        <select name="branch_id" id="{{ $prefix }}_branch_id" class="form-select">
            <option value="">All my authorized branches</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $schedule?->branch_id) === (string) $branch->id)>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Branch managers and branch-scoped recipients are honored on every run.</div>
    </div>
@endif

<div class="row g-2 mb-3">
    <div class="col-md-6">
        <label for="{{ $prefix }}_recipient_mode" class="form-label">Recipients</label>
        <select name="recipient_mode" id="{{ $prefix }}_recipient_mode" class="form-select" required>
            @foreach ($scheduleRecipientModes as $mode)
                <option value="{{ $mode->value }}" @selected(old('recipient_mode', $schedule?->recipient_mode?->value) === $mode->value)>
                    {{ $mode->label() }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Every recipient is re-verified before delivery on each run.</div>
    </div>

    <div class="col-md-6" id="{{ $prefix }}_recipients_wrap">
        <label for="{{ $prefix }}_recipients" class="form-label">Chosen recipients <span class="text-muted small">(specific users)</span></label>
        @if ($scheduleRecipients->isEmpty())
            <div class="alert alert-warning py-2 mb-0 small">No authorized users are available to receive this report.</div>
        @else
            <select name="recipients[]" id="{{ $prefix }}_recipients" class="form-select" multiple size="4">
                @foreach ($scheduleRecipients as $recipient)
                    <option value="{{ $recipient->id }}" @selected(in_array($recipient->id, array_map('intval', (array) $selectedRecipients), true))>
                        {{ $recipient->fullname }}
                    </option>
                @endforeach
            </select>
        @endif
    </div>
</div>

<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="include_narrative" value="1"
           id="{{ $prefix }}_include_narrative"
           @checked(old('include_narrative', $schedule?->include_narrative))>
    <label class="form-check-label" for="{{ $prefix }}_include_narrative">
        Include the optional AI narrative in the delivered digest
    </label>
    <div class="form-text">
        Advisory prose only, clearly labelled. If the AI provider is unavailable the report is still delivered in full.
    </div>
</div>