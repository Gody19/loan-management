<?php

namespace App\AI\Reporting\Scheduling;

use App\AI\DTOs\AiContextData;
use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleRecipientMode;
use App\Enums\ReportType;
use App\Models\AiReportSchedule;
use App\Models\AiReportScheduleRun;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the configuration lifecycle of recurring management reports
 * (Phase 12.1): validation, tenant scope, the deterministic next-run moment,
 * enable/disable and deletion — every transition audited.
 *
 * Three invariants are enforced here rather than in the controller:
 *
 *  1. **No privilege escalation.** A schedule may only be created inside the
 *     acting user's own authoritative organization and branch scope, and a
 *     branch-scoped schedule additionally requires that branch to belong to
 *     that organization. The organization is never read from the request.
 *  2. **The schedule cannot widen access.** Producing an accounting report
 *     requires the accounting capability at creation time, and delivery
 *     re-verifies every recipient on every run, so revoking somebody's access
 *     stops their delivery immediately.
 *  3. **A schedule is deterministic.** Frequencies come from a closed
 *     vocabulary and the next run is always computed strictly forward in the
 *     schedule's own stored timezone, so a schedule can never be pinned to the
 *     past or silently drift when the application timezone changes.
 */
class AiReportScheduleService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Schedules the acting user may administer, newest first.
     *
     * @return EloquentCollection<int, AiReportSchedule>
     */
    public function listFor(AiContextData $context): EloquentCollection
    {
        return AiReportSchedule::with(['organization', 'branch', 'creator'])
            ->forOrganizations($context->organizationIds)
            ->when($context->branchIds !== [], function ($query) use ($context) {
                $query->where(function ($inner) use ($context) {
                    $inner->whereNull('branch_id')->orWhereIn('branch_id', $context->branchIds);
                });
            })
            ->orderByDesc('id')
            ->limit((int) config('intelligence-reporting.scheduling.max_schedules_listed', 100))
            ->get();
    }

    /**
     * Create a schedule inside the acting user's authorized scope.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(AiContextData $context, User $user, array $attributes): AiReportSchedule
    {
        // Defence in depth behind the route middleware: creating a schedule
        // requires both the scheduling capability and the reporting capability.
        if (! $context->hasPermission('ai.reports.schedule') || ! $context->hasPermission('ai.reports.view')) {
            throw new InvalidArgumentException('Unauthorized report schedule.');
        }

        if ($context->organizationIds === []) {
            throw new InvalidArgumentException('No authorized organization for a report schedule.');
        }

        $organizationId = (int) $context->organizationIds[0];
        $reportType = $this->resolveReportType($attributes['report_type'] ?? null, $context);
        $frequency = $this->resolveFrequency($attributes['frequency'] ?? null);
        $branchId = $this->resolveBranch($context, $attributes['branch_id'] ?? null, $organizationId);
        $recipientMode = $this->resolveRecipientMode($attributes['recipient_mode'] ?? null);
        $timezone = $this->resolveTimezone($attributes['timezone'] ?? null);
        $runTime = $this->normalizeRunTime($attributes['run_time'] ?? null);
        $weekday = $this->resolveWeekday($frequency, $attributes['weekday'] ?? null);
        $dayOfMonth = $this->resolveDayOfMonth($frequency, $attributes['day_of_month'] ?? null);
        $recipients = $this->normalizeRecipients(
            $recipientMode,
            $attributes['recipients'] ?? null,
            $organizationId,
            $branchId,
        );

        $nextRunAt = $frequency->nextRunAfter(
            CarbonImmutable::now($timezone),
            $runTime,
            $weekday,
            $dayOfMonth,
        );

        $schedule = DB::transaction(fn () => AiReportSchedule::create([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'name' => $this->normalizeName($attributes['name'] ?? null),
            'report_type' => $reportType->value,
            'frequency' => $frequency->value,
            'run_time' => $runTime,
            'weekday' => $weekday,
            'day_of_month' => $dayOfMonth,
            'timezone' => $timezone,
            'recipient_mode' => $recipientMode->value,
            'recipients' => $recipients,
            'include_narrative' => (bool) ($attributes['include_narrative'] ?? false),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'next_run_at' => $nextRunAt,
            'created_by' => $user->getAuthIdentifier(),
            'updated_by' => $user->getAuthIdentifier(),
        ]));

        $this->audit->log('ai.report_schedule.created', $schedule, [], [
            'organization_id' => $schedule->organization_id,
            'branch_id' => $schedule->branch_id,
            'report_type' => $schedule->report_type->value,
            'frequency' => $schedule->frequency->value,
            'timezone' => $schedule->timezone,
            'recipient_mode' => $schedule->recipient_mode->value,
            'recipient_count' => $schedule->recipients === null ? 0 : count($schedule->recipients),
            'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
        ]);

        return $schedule;
    }

    /**
     * Update a schedule the acting user may administer. A changed frequency or
     * timing always recomputes the next run strictly forward, so editing can
     * never schedule a run in the past.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(AiContextData $context, User $user, AiReportSchedule $schedule, array $attributes): AiReportSchedule
    {
        $this->assertManageable($context, $schedule);

        $before = $schedule->only([
            'name', 'report_type', 'frequency', 'run_time', 'weekday',
            'day_of_month', 'timezone', 'recipient_mode', 'recipients',
            'include_narrative', 'is_active', 'next_run_at',
        ]);

        $organizationId = (int) $schedule->organization_id;
        $reportType = array_key_exists('report_type', $attributes)
            ? $this->resolveReportType($attributes['report_type'], $context)
            : $schedule->report_type;
        $frequency = array_key_exists('frequency', $attributes)
            ? $this->resolveFrequency($attributes['frequency'])
            : $schedule->frequency;
        $branchId = array_key_exists('branch_id', $attributes)
            ? $this->resolveBranch($context, $attributes['branch_id'], $organizationId)
            : $schedule->branch_id;
        $recipientMode = array_key_exists('recipient_mode', $attributes)
            ? $this->resolveRecipientMode($attributes['recipient_mode'])
            : $schedule->recipient_mode;
        $timezone = array_key_exists('timezone', $attributes)
            ? $this->resolveTimezone($attributes['timezone'])
            : $schedule->timezone;
        $runTime = array_key_exists('run_time', $attributes)
            ? $this->normalizeRunTime($attributes['run_time'])
            : $this->normalizeRunTime($schedule->run_time);
        $weekday = array_key_exists('weekday', $attributes) || $frequency !== $schedule->frequency
            ? $this->resolveWeekday($frequency, $attributes['weekday'] ?? $schedule->weekday)
            : $schedule->weekday;
        $dayOfMonth = array_key_exists('day_of_month', $attributes) || $frequency !== $schedule->frequency
            ? $this->resolveDayOfMonth($frequency, $attributes['day_of_month'] ?? $schedule->day_of_month)
            : $schedule->day_of_month;

        // The audience is re-validated whenever it is touched, and also whenever
        // the tenant scope narrows, so a schedule can never keep delivering to
        // people who lost access to the organization or branch.
        $recipients = (array_key_exists('recipients', $attributes) || $recipientMode !== $schedule->recipient_mode || $branchId !== $schedule->branch_id)
            ? $this->normalizeRecipients(
                $recipientMode,
                $attributes['recipients'] ?? $schedule->recipients,
                $organizationId,
                $branchId,
            )
            : $schedule->recipients;

        // Timing changed, so the next run is recomputed from now in the schedule's
        // timezone rather than left pointing at a stale moment.
        $timingChanged = $frequency !== $schedule->frequency
            || $runTime !== $this->normalizeRunTime($schedule->run_time)
            || $timezone !== $schedule->timezone
            || $weekday !== $schedule->weekday
            || $dayOfMonth !== $schedule->day_of_month;

        $schedule->update([
            'branch_id' => $branchId,
            'name' => array_key_exists('name', $attributes) ? $this->normalizeName($attributes['name']) : $schedule->name,
            'report_type' => $reportType->value,
            'frequency' => $frequency->value,
            'run_time' => $runTime,
            'weekday' => $weekday,
            'day_of_month' => $dayOfMonth,
            'timezone' => $timezone,
            'recipient_mode' => $recipientMode->value,
            'recipients' => $recipients,
            'include_narrative' => array_key_exists('include_narrative', $attributes)
                ? (bool) $attributes['include_narrative']
                : $schedule->include_narrative,
            'updated_by' => $user->getAuthIdentifier(),
        ]);

        if ($timingChanged) {
            $schedule->update([
                'next_run_at' => $frequency->nextRunAfter(
                    CarbonImmutable::now($timezone),
                    $runTime,
                    $weekday,
                    $dayOfMonth,
                ),
            ]);
        }

        $this->audit->log('ai.report_schedule.updated', $schedule, $before, [
            'report_type' => $schedule->report_type->value,
            'frequency' => $schedule->frequency->value,
            'timezone' => $schedule->timezone,
            'recipient_mode' => $schedule->recipient_mode->value,
            'timing_changed' => $timingChanged,
            'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
        ]);

        return $schedule;
    }

    /**
     * Enable or disable a schedule. Disabling stops future runs but never
     * deletes the history, so an inactive schedule still shows what it produced.
     */
    public function setActive(AiContextData $context, User $user, AiReportSchedule $schedule, bool $active): AiReportSchedule
    {
        $this->assertManageable($context, $schedule);

        $wasActive = (bool) $schedule->is_active;

        if ($wasActive !== $active) {
            $schedule->update([
                'is_active' => $active,
                // Re-enabling recomputes from now so an enabled schedule that was
                // disabled for a month does not immediately fire a stale window.
                'next_run_at' => $active
                    ? $schedule->frequency->nextRunAfter(
                        CarbonImmutable::now($schedule->timezone),
                        $this->normalizeRunTime($schedule->run_time),
                        $schedule->weekday,
                        $schedule->day_of_month,
                    )
                    : null,
                'updated_by' => $user->getAuthIdentifier(),
            ]);

            $this->audit->log($active ? 'ai.report_schedule.enabled' : 'ai.report_schedule.disabled', $schedule, [
                'is_active' => $wasActive,
            ], [
                'is_active' => $active,
                'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
            ]);
        }

        return $schedule;
    }

    /**
     * Delete a schedule. The run history is kept by cascade only while the
     * schedule exists, so the deletion itself is audited with its id first.
     */
    public function delete(AiContextData $context, User $user, AiReportSchedule $schedule): void
    {
        $this->assertManageable($context, $schedule);

        $this->audit->log('ai.report_schedule.deleted', $schedule, [
            'name' => $schedule->name,
            'report_type' => $schedule->report_type->value,
            'frequency' => $schedule->frequency->value,
            'is_active' => (bool) $schedule->is_active,
        ], []);

        $organizationId = (int) $schedule->organization_id;

        $schedule->delete();

        $this->audit->log('ai.report_schedule.deleted', null, [], [
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * The recent executions of a schedule, for the dashboard.
     *
     * @return EloquentCollection<int, AiReportScheduleRun>
     */
    public function recentRuns(AiReportSchedule $schedule, int $limit = 10): EloquentCollection
    {
        return AiReportScheduleRun::with(['report'])
            ->forSchedule((int) $schedule->id)
            ->orderByDesc('id')
            ->limit(min(max(1, $limit), 25))
            ->get();
    }

    /**
     * The currently authorized recipients of a schedule.
     *
     * Authorization is evaluated here, at delivery time, against the current
     * authoritative state — never against a list frozen when the schedule was
     * created. A recipient is kept only when they still belong to the scheduled
     * organization, still hold the reporting capability, and (for a branch
     * schedule) are still assigned to that branch. The organization and branch
     * therefore come from the schedule, which itself was authorized server-side
     * at creation; nothing here is request supplied.
     *
     * @return EloquentCollection<int, User>
     */
    public function resolveRecipients(AiReportSchedule $schedule): EloquentCollection
    {
        if (! (bool) config('intelligence-reporting.scheduling.notify', true)) {
            return new EloquentCollection;
        }

        $query = User::query()
            ->permission('ai.reports.view')
            ->whereHas('organizations', fn ($query) => $query->whereKey($schedule->organization_id));

        if ($schedule->branch_id !== null) {
            $query->whereHas('branches', fn ($query) => $query->whereKey($schedule->branch_id));
        }

        if ($schedule->recipient_mode === ReportScheduleRecipientMode::SpecificUsers) {
            $ids = array_values(array_filter(array_map('intval', (array) $schedule->recipients)));

            if ($ids === []) {
                return new EloquentCollection;
            }

            $query->whereKey($ids);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Users who may be picked as explicit recipients: currently authorized in
     * the given organization, and holding the reporting capability. The caller
     * has already established that the organization and branch are inside the
     * acting user's own trusted scope.
     *
     * @return EloquentCollection<int, User>
     */
    public function candidateRecipients(int $organizationId, ?int $branchId = null): EloquentCollection
    {
        return User::query()
            ->permission('ai.reports.view')
            ->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))
            ->when($branchId !== null, fn ($query) => $query->whereHas('branches', fn ($inner) => $inner->whereKey($branchId)))
            ->orderBy('fullname')
            ->get();
    }

    /**
     * A schedule the acting user may administer. A schedule outside the trusted
     * scope is reported as not found rather than forbidden, so its existence is
     * never disclosed.
     */
    public function findManageable(AiContextData $context, int $scheduleId): AiReportSchedule
    {
        $schedule = AiReportSchedule::with(['organization', 'branch'])
            ->forOrganizations($context->organizationIds)
            ->orderByDesc('id')
            ->firstWhere('id', $scheduleId);

        if ($schedule === null) {
            throw new InvalidArgumentException('Report schedule not found.');
        }

        if ($schedule->branch_id !== null
            && $context->branchIds !== []
            && ! $context->belongsToBranch((int) $schedule->branch_id)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }

        return $schedule;
    }

    /**
     * Managing schedules is gated by ai.reports.schedule *and* the reporting
     * capability itself: someone who cannot read reports must never be able to
     * configure their automatic distribution.
     */
    protected function assertManageable(AiContextData $context, AiReportSchedule $schedule): void
    {
        if (! $context->hasPermission('ai.reports.schedule') || ! $context->hasPermission('ai.reports.view')) {
            throw new InvalidArgumentException('Unauthorized report schedule.');
        }

        if (! $context->belongsToOrganization((int) $schedule->organization_id)) {
            throw new InvalidArgumentException('Unauthorized organization scope.');
        }

        if ($schedule->branch_id !== null
            && $context->branchIds !== []
            && ! $context->belongsToBranch((int) $schedule->branch_id)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }
    }

    /**
     * Branch narrowing is accepted only inside the acting user's trusted scope,
     * and the branch must belong to the schedule's own organization — so a
     * branch of another organization can never be scheduled, even by a user who
     * legitimately belongs to both.
     */
    protected function resolveBranch(AiContextData $context, mixed $value, int $organizationId): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }

        $branch = Branch::find((int) $value);

        if ($branch === null
            || (int) $branch->organization_id !== $organizationId
            || ! $context->belongsToOrganization((int) $branch->organization_id)
            || ! $context->belongsToBranch((int) $branch->id)) {
            throw new InvalidArgumentException('Unauthorized branch scope.');
        }

        return (string) $branch->id;
    }

    protected function resolveReportType(mixed $value, AiContextData $context): ReportType
    {
        $type = is_string($value) ? ReportType::tryFrom($value) : null;

        if ($type === null) {
            throw new InvalidArgumentException('Unsupported report type.');
        }

        // The reporting layer never widens a role's domain access: an accounting
        // report schedule requires the accounting capability, exactly as a
        // manual generation does.
        if ($type->requiresAccountingCapability() && ! $context->hasPermission('ai.accounting.view')) {
            throw new InvalidArgumentException('Unauthorized report type.');
        }

        return $type;
    }

    protected function resolveFrequency(mixed $value): ReportScheduleFrequency
    {
        $frequency = is_string($value) ? ReportScheduleFrequency::tryFrom($value) : null;

        if ($frequency === null) {
            throw new InvalidArgumentException('Unsupported schedule frequency.');
        }

        return $frequency;
    }

    protected function resolveRecipientMode(mixed $value): ReportScheduleRecipientMode
    {
        $mode = is_string($value) ? ReportScheduleRecipientMode::tryFrom($value) : null;

        if ($mode === null) {
            throw new InvalidArgumentException('Unsupported recipient mode.');
        }

        return $mode;
    }

    /**
     * The effective timezone of a schedule. Always stored explicitly, so the
     * reporting day can never silently move if the application timezone changes
     * later. An unknown identifier is rejected rather than silently defaulted.
     */
    protected function resolveTimezone(mixed $value): string
    {
        $fallback = (string) config('app.timezone', 'UTC');

        if ($value === null || $value === '') {
            return in_array($fallback, timezone_identifiers_list(), true) ? $fallback : 'UTC';
        }

        if (! is_string($value) || ! in_array($value, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Unsupported schedule timezone.');
        }

        return $value;
    }

    protected function normalizeName(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('A report schedule requires a name.');
        }

        return mb_substr(trim($value), 0, 120);
    }

    protected function normalizeRunTime(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('A report schedule requires a run time.');
        }

        $trimmed = trim($value);

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $trimmed) !== 1) {
            throw new InvalidArgumentException('The schedule time must be a valid time of day.');
        }

        $parts = explode(':', $trimmed);

        $hour = (int) $parts[0];
        $minute = (int) $parts[1];
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidArgumentException('The schedule time must be a valid time of day.');
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    protected function resolveWeekday(ReportScheduleFrequency $frequency, mixed $value): ?int
    {
        if (! $frequency->requiresWeekday()) {
            return null;
        }

        $weekday = is_numeric($value) ? (int) $value : null;

        if ($weekday === null || $weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException('A weekly schedule requires a weekday between 1 and 7.');
        }

        return $weekday;
    }

    protected function resolveDayOfMonth(ReportScheduleFrequency $frequency, mixed $value): ?int
    {
        if (! $frequency->requiresDayOfMonth()) {
            return null;
        }

        $day = is_numeric($value) ? (int) $value : 1;

        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException('A monthly or quarterly schedule requires a day of the month between 1 and 31.');
        }

        return $day;
    }

    /**
     * Explicit recipients are validated as real, currently authorized users of
     * the schedule's own organization and branch, so a schedule can never be
     * pointed at somebody outside the tenant — or at a user who could not read
     * the report anyway.
     *
     * @return array<int, int>|null
     */
    protected function normalizeRecipients(
        ReportScheduleRecipientMode $mode,
        mixed $value,
        int $organizationId,
        ?string $branchId,
    ): ?array {
        if (! $mode->requiresRecipients()) {
            return null;
        }

        $ids = is_array($value)
            ? array_values(array_unique(array_filter(array_map('intval', $value))))
            : [];

        if ($ids === []) {
            throw new InvalidArgumentException('This recipient mode requires at least one authorized recipient.');
        }

        $authorized = $this->candidateRecipients(
            $organizationId,
            $branchId !== null ? (int) $branchId : null,
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $invalid = array_diff($ids, $authorized);

        if ($invalid !== []) {
            throw new InvalidArgumentException('One or more recipients are not authorized for this report schedule.');
        }

        return $ids;
    }
}
