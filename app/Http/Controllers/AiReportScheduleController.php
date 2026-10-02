<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\Reporting\Scheduling\AiReportScheduleRunner;
use App\AI\Reporting\Scheduling\AiReportScheduleService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleRecipientMode;
use App\Enums\ReportScheduleRunStatus;
use App\Enums\ReportType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Administration of recurring management reports (Phase 12.1).
 *
 * A thin HTTP layer over AiReportScheduleService and AiReportScheduleRunner: the
 * controller validates the request shape, resolves the trusted AI context and
 * redirects. It performs no tenant decision, no scheduling arithmetic and no
 * financial computation of its own.
 *
 * The recurring surface is gated by `ai.reports.schedule`, which is granted
 * independently of the read-only `ai.reports.view`: scheduling automatic
 * distribution is a management configuration act, not a reporting read. Every
 * mutating action additionally re-checks the capability inside the service, so a
 * schedule outside the trusted scope is denied even if the route middleware
 * were bypassed.
 *
 * The read-only scheduling state (the schedule list, its recent runs and the
 * recipient form) is rendered on the reporting dashboard, which reuses the same
 * report types and branches the manual generation form already resolves.
 */
class AiReportScheduleController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly AiReportScheduleService $schedules,
        private readonly AiReportScheduleRunner $runner,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $this->validatePayload($request, $context);

        try {
            $this->schedules->create($context, $user, $this->attributes($validated));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Report schedule created.');
    }

    public function update(Request $request, int $schedule): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $this->validatePayload($request, $context);

        try {
            $model = $this->schedules->findManageable($context, $schedule);
            $this->schedules->update($context, $user, $model, $this->attributes($validated));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Report schedule updated.');
    }

    public function toggle(Request $request, int $schedule): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        try {
            $model = $this->schedules->findManageable($context, $schedule);
            $active = ! (bool) $model->is_active;
            $this->schedules->setActive($context, $user, $model, $active);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $active ? 'Report schedule enabled.' : 'Report schedule disabled.');
    }

    /**
     * Execute a schedule immediately, using the *same* idempotent path as the
     * scheduler. A manual run never modifies the schedule definition; when the
     * current period has already been produced, the existing run is reported
     * instead of generating a duplicate.
     */
    public function runNow(Request $request, int $schedule): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        try {
            $model = $this->schedules->findManageable($context, $schedule);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // An inactive schedule may still be run by hand: the explicit human
        // action is the authorization, and it changes nothing about the schedule.
        $run = $this->runner->run($model, 'manual', $user);

        if ($run->replayed) {
            return back()->with(
                'info',
                sprintf(
                    'This schedule already executed for %s to %s (status: %s). Nothing was regenerated.',
                    $run->period_start->toDateString(),
                    $run->period_end->toDateString(),
                    $run->status->label(),
                ),
            );
        }

        return match ($run->status) {
            ReportScheduleRunStatus::Completed => back()->with(
                'success',
                sprintf(
                    'Report generated for %s to %s and delivered to %d recipient(s).',
                    $run->period_start->toDateString(),
                    $run->period_end->toDateString(),
                    $run->notifications_sent,
                ),
            ),
            ReportScheduleRunStatus::Skipped => back()->with(
                'info',
                $run->failure_reason ?? 'This schedule has already run for the current period.',
            ),
            default => back()->with(
                'error',
                $run->failure_reason ?? 'The scheduled report could not be generated.',
            ),
        };
    }

    public function destroy(Request $request, int $schedule): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        try {
            $model = $this->schedules->findManageable($context, $schedule);
            $this->schedules->delete($context, $user, $model);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Report schedule deleted.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'] ?? null,
            'report_type' => $validated['report_type'] ?? null,
            'frequency' => $validated['frequency'] ?? null,
            'run_time' => $validated['run_time'] ?? null,
            'weekday' => $validated['weekday'] ?? null,
            'day_of_month' => $validated['day_of_month'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'recipient_mode' => $validated['recipient_mode'] ?? null,
            'recipients' => $validated['recipients'] ?? null,
            'include_narrative' => $validated['include_narrative'] ?? false,
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, AiContextData $context): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'report_type' => ['required', Rule::in(array_column($this->scheduleReportTypes($context), 'value'))],
            'frequency' => ['required', Rule::in(ReportScheduleFrequency::values())],
            'run_time' => ['required', 'date_format:H:i'],
            'weekday' => ['nullable', 'integer', 'between:1,7', 'required_if:frequency,weekly'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31', 'required_if:frequency,monthly', 'required_if:frequency,quarterly'],
            'timezone' => ['nullable', 'timezone'],
            'recipient_mode' => ['required', Rule::in(ReportScheduleRecipientMode::values())],
            'recipients' => ['nullable', 'array', 'required_if:recipient_mode,specific_users'],
            'recipients.*' => ['integer'],
            'include_narrative' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @return array<int, ReportType>
     */
    protected function scheduleReportTypes(AiContextData $context): array
    {
        // The scheduler never widens access: the same accounting-capability rule
        // that governs a manual generation governs a schedule.
        return array_values(array_filter(
            ReportType::cases(),
            fn (ReportType $type) => ! $type->requiresAccountingCapability() || $context->hasPermission('ai.accounting.view'),
        ));
    }
}
