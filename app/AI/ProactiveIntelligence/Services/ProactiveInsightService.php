<?php

namespace App\AI\ProactiveIntelligence\Services;

use App\AI\DTOs\AiContextData;
use App\Enums\ProactiveInsightStatus;
use App\Models\AiInsight;
use App\Models\User;
use App\Notifications\AiInsightNotification;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Proactive Intelligence orchestrator (Phase 11.9).
 *
 * Runs the deterministic detection rules for an organization, persists each
 * result idempotently under a stable dedup key, retires insights whose
 * condition stopped holding (expiry driven by domain state, never by the AI),
 * and exposes read / acknowledge / resolve / dismiss transitions. Every
 * generation and lifecycle change is audited. The layer is advisory only: an
 * insight never changes a financial record and never makes a decision.
 */
class ProactiveInsightService
{
    public function __construct(
        private readonly ProactiveInsightDetectionService $detection,
        private readonly AuditService $audit,
    ) {}

    /**
     * Run a generation pass across all given organizations (scheduler entry
     * point).
     *
     * @param  int[]  $organizationIds
     * @return array<string, int>
     */
    public function runForOrganizations(array $organizationIds, ?User $caller = null): array
    {
        $summary = ['organizations' => 0, 'created' => 0, 'updated' => 0, 'expired' => 0, 'notifications' => 0];

        foreach ($organizationIds as $organizationId) {
            $result = $this->generate((int) $organizationId, [], $caller);

            $summary['organizations']++;
            $summary['created'] += $result['created'];
            $summary['updated'] += $result['updated'];
            $summary['expired'] += $result['expired'];
            $summary['notifications'] += $result['notifications'];
        }

        return $summary;
    }

    /**
     * Detect, persist and retire insights for one organization.
     *
     * @param  int[]  $branchIds
     * @return array{created: int, updated: int, expired: int, notifications: int, active: int}
     */
    public function generate(int $organizationId, array $branchIds = [], ?User $caller = null): array
    {
        $raws = $this->detection->detect($organizationId, $branchIds);

        $keys = [];
        $created = 0;
        $updated = 0;
        $notifications = 0;

        foreach ($raws as $raw) {
            $key = $this->dedupKey($organizationId, $raw);
            $wasExisting = AiInsight::where('dedup_key', $key)->exists();

            $insight = $this->persist($organizationId, $raw, $key);

            if ($wasExisting) {
                $updated++;
            } else {
                $created++;

                if ((bool) config('proactive-intelligence.notify', true)) {
                    $notifications += $this->dispatchNotifications($insight);
                }
            }

            $keys[] = $key;
        }

        $expired = $this->expire($organizationId, $keys);

        $this->audit->log('ai.insights.generated', null, [], [
            'organization_id' => $organizationId,
            'branch_count' => count($branchIds),
            'rules' => array_values(array_unique(array_map(fn (array $raw) => $raw['rule'], $raws))),
            'created' => $created,
            'updated' => $updated,
            'expired' => $expired,
            'active' => count($keys),
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'expired' => $expired,
            'notifications' => $notifications,
            'active' => count($keys),
        ];
    }

    /**
     * Persist one raw detection under its dedup key. The key is unique at the
     * database level so concurrent passes can never duplicate a row. A
     * human-dismissed insight is never overwritten; a resolved/expired one is
     * reopened (the condition re-occurred).
     *
     * @param  array<string, mixed>  $raw
     */
    protected function persist(int $organizationId, array $raw, string $key): AiInsight
    {
        $existing = AiInsight::where('dedup_key', $key)->first();

        if ($existing !== null && $existing->status === ProactiveInsightStatus::Dismissed) {
            return $existing;
        }

        $values = [
            'branch_id' => $raw['branch_id'],
            'type' => $raw['type'],
            'severity' => $raw['severity'],
            'title' => $raw['title'],
            'summary' => $raw['summary'],
            'recommendation' => $raw['recommendation'],
            'source_type' => $raw['source_type'],
            'source_id' => $raw['source_id'],
            'object_type' => $raw['object_type'],
            'object_id' => $raw['object_id'],
            'period_start' => $raw['period_start'],
            'period_end' => $raw['period_end'],
            'metadata' => $raw['metadata'] + ['rule' => $raw['rule']],
            'generated_at' => now(),
            'data_through' => $raw['data_through'],
        ];

        if ($existing === null) {
            try {
                return AiInsight::create($values + [
                    'organization_id' => $organizationId,
                    'dedup_key' => $key,
                    'status' => ProactiveInsightStatus::New->value,
                ]);
            } catch (QueryException) {
                $existing = AiInsight::where('dedup_key', $key)->first();

                if ($existing !== null && $existing->status !== ProactiveInsightStatus::Dismissed) {
                    return $this->reopenOrUpdate($existing, $values);
                }

                return $existing ?? AiInsight::create($values + [
                    'organization_id' => $organizationId,
                    'dedup_key' => $key,
                    'status' => ProactiveInsightStatus::New->value,
                ]);
            }
        }

        return $this->reopenOrUpdate($existing, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function reopenOrUpdate(AiInsight $insight, array $values): AiInsight
    {
        $wasTerminal = in_array($insight->status, [
            ProactiveInsightStatus::Resolved,
            ProactiveInsightStatus::Expired,
        ], true);

        $insight->update($values);

        if ($wasTerminal) {
            $insight->update([
                'status' => ProactiveInsightStatus::New->value,
                'acknowledged_by' => null,
                'acknowledged_at' => null,
                'resolved_by' => null,
                'resolved_at' => null,
                'dismissed_by' => null,
                'dismissed_at' => null,
                'expired_at' => null,
            ]);
        }

        return $insight->refresh();
    }

    /**
     * Retire open insights whose generation has aged past stale_after_days and
     * whose condition (dedup key) is no longer produced by the current rule
     * set. $currentKeys may be reused from the same pass.
     *
     * @param  int[]|null  $currentKeys
     */
    public function expire(int $organizationId, ?array $currentKeys = null): int
    {
        $currentKeys ??= array_map(
            fn (array $raw) => $this->dedupKey($organizationId, $raw),
            $this->detection->detect($organizationId),
        );

        $threshold = now()->subDays(max(0, (int) config('proactive-intelligence.stale_after_days', 7)));

        $rows = AiInsight::forOrganization($organizationId)
            ->open()
            ->where('generated_at', '<', $threshold)
            ->get();

        $expired = 0;

        foreach ($rows as $row) {
            if (in_array($row->dedup_key, $currentKeys, true)) {
                continue;
            }

            $row->update([
                'status' => ProactiveInsightStatus::Expired->value,
                'expired_at' => now(),
            ]);

            $expired++;
        }

        return $expired;
    }

    /**
     * Convenience for tests / one-off sweeps: run detection and expire.
     *
     * @param  int[]  $branchIds
     */
    public function expireFor(int $organizationId, array $branchIds = []): int
    {
        $currentKeys = array_map(
            fn (array $raw) => $this->dedupKey($organizationId, $raw),
            $this->detection->detect($organizationId, $branchIds),
        );

        return $this->expire($organizationId, $currentKeys);
    }

    /**
     * Open insights for the trusted context, severity-first, eager-loaded with
     * the owning organization.
     *
     * @return Collection<int, AiInsight>
     */
    public function forDashboard(AiContextData $context): Collection
    {
        $insights = AiInsight::with('organization')
            ->forOrganizations($context->organizationIds)
            ->open()
            ->when($context->branchIds !== [], function ($query) use ($context) {
                $query->where(function ($inner) use ($context) {
                    $inner->whereNull('branch_id')
                        ->orWhereIn('branch_id', $context->branchIds);
                });
            })
            ->orderByDesc('generated_at')
            ->get();

        return $insights
            ->sortByDesc(fn (AiInsight $insight) => $insight->severity->priority())
            ->values();
    }

    /**
     * Mark an open insight as acknowledged (a human decision — never AI).
     */
    public function acknowledge(AiInsight $insight, User $user): AiInsight
    {
        if (! in_array($insight->status, [
            ProactiveInsightStatus::New,
            ProactiveInsightStatus::Read,
        ], true)) {
            return $insight;
        }

        $previous = $insight->status->value;

        $insight->update([
            'status' => ProactiveInsightStatus::Acknowledged->value,
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);

        $this->audit->log('ai.insight.acknowledged', $insight, ['status' => $previous], [
            'status' => ProactiveInsightStatus::Acknowledged->value,
            'by' => $user->id,
            'organization_id' => $insight->organization_id,
        ]);

        return $insight->refresh();
    }

    /**
     * Resolve an open insight (the condition is handled).
     */
    public function resolve(AiInsight $insight, User $user): AiInsight
    {
        if (! $insight->status->isOpen()) {
            return $insight;
        }

        $previous = $insight->status->value;

        $insight->update([
            'status' => ProactiveInsightStatus::Resolved->value,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        $this->audit->log('ai.insight.resolved', $insight, ['status' => $previous], [
            'status' => ProactiveInsightStatus::Resolved->value,
            'by' => $user->id,
            'organization_id' => $insight->organization_id,
        ]);

        return $insight->refresh();
    }

    /**
     * Dismiss an open insight (human decision; never recreated by later
     * passes).
     */
    public function dismiss(AiInsight $insight, User $user): AiInsight
    {
        if (! $insight->status->isOpen()) {
            return $insight;
        }

        $previous = $insight->status->value;

        $insight->update([
            'status' => ProactiveInsightStatus::Dismissed->value,
            'dismissed_by' => $user->id,
            'dismissed_at' => now(),
        ]);

        $this->audit->log('ai.insight.dismissed', $insight, ['status' => $previous], [
            'status' => ProactiveInsightStatus::Dismissed->value,
            'by' => $user->id,
            'organization_id' => $insight->organization_id,
        ]);

        return $insight->refresh();
    }

    /**
     * Stable dedup identity of a detection: org, branch, rule, source and
     * period. Mirrors the spec key (org / branch / type / source / source /
     * period), using the stable rule code in place of the display category of
     * type so sibling rules (e.g. both accounting categories) never collide.
     *
     * @param  array<string, mixed>  $raw
     */
    protected function dedupKey(int $organizationId, array $raw): string
    {
        return implode('|', [
            'org:'.$organizationId,
            'branch:'.($raw['branch_id'] ?? 'null'),
            'rule:'.($raw['rule'] ?? $raw['type']),
            'source:'.$raw['source_type'].':'.($raw['source_id'] ?? 'null'),
            'period:'.($raw['period_start'] ?? 'none'),
        ]);
    }

    /**
     * Push an in-app notification for a brand-new insight to every user
     * holding ai.insights.view in the owning organization.
     */
    protected function dispatchNotifications(AiInsight $insight): int
    {
        $users = User::query()
            ->permission('ai.insights.view')
            ->whereHas('organizations', function ($query) use ($insight) {
                $query->whereKey($insight->organization_id);
            })
            ->get();

        DB::transaction(function () use ($users, $insight) {
            foreach ($users as $user) {
                $user->notify(new AiInsightNotification($insight));
            }
        });

        return $users->count();
    }
}
