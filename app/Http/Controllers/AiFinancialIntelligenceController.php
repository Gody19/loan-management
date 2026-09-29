<?php

namespace App\Http\Controllers;

use App\AI\FinancialIntelligence\Services\AccountingIntelligenceService;
use App\AI\FinancialIntelligence\Services\CollectionIntelligenceService;
use App\AI\FinancialIntelligence\Services\DelinquencyIntelligenceService;
use App\AI\FinancialIntelligence\Services\FinancialAnomalyDetectionService;
use App\AI\FinancialIntelligence\Services\FinancialTrendService;
use App\AI\FinancialIntelligence\Services\ParIntelligenceService;
use App\AI\FinancialIntelligence\Services\PortfolioIntelligenceService;
use App\AI\PredictiveIntelligence\Services\PredictiveIntelligenceService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\PredictiveInsightType;
use App\Models\AiAnomalyFinding;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Financial Intelligence dashboard (Phase 11.7).
 *
 * A read-only presentation of the descriptive intelligence summaries. Tenant
 * and branch scope come exclusively from the trusted AI context (the six
 * ai.*.view capabilities are permission-gated at the route, role grants in the
 * seeder, and re-checked here so each section renders only for its holder).
 * Detection of anomalies is deterministic and persists a day-keyed review
 * trail; the only write here is a human review marker, never a business rule.
 */
class AiFinancialIntelligenceController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly PortfolioIntelligenceService $portfolio,
        private readonly ParIntelligenceService $par,
        private readonly DelinquencyIntelligenceService $delinquency,
        private readonly CollectionIntelligenceService $collections,
        private readonly FinancialTrendService $trends,
        private readonly AccountingIntelligenceService $accounting,
        private readonly FinancialAnomalyDetectionService $detection,
        private readonly PredictiveIntelligenceService $predictive,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $data = [];

        if ($user->can('ai.portfolio.view')) {
            $data['portfolio'] = $this->portfolio->summarize($context->organizationIds, $context->branchIds);
        }

        if ($user->can('ai.delinquency.view')) {
            $data['par'] = $this->par->summarize($context->organizationIds, $context->branchIds);
            $data['delinquency'] = $this->delinquency->profile($context->organizationIds, $context->branchIds);
        }

        if ($user->can('ai.collection.view')) {
            $data['collections'] = $this->collections->summarize($context->organizationIds, $context->branchIds);
        }

        if ($user->can('ai.trend.view')) {
            $data['trends'] = $this->trends->monthly($context->organizationIds, $context->branchIds);
        }

        if ($user->can('ai.accounting.view')) {
            $data['accounting'] = $this->accounting->summarize($context->organizationIds);
        }

        if ($user->can('ai.anomaly.view')) {
            $data['findings'] = $this->detection->detect($context->organizationIds);
        }

        if ($user->can('ai.predictive.view')) {
            $data['predictive'] = $this->predictive->forDashboard($context);
        }

        $this->audit->log('ai.financial_intelligence.viewed', null, [], [
            'organization_count' => count($context->organizationIds),
            'branch_count' => count($context->branchIds),
            'domains' => array_keys($data),
        ]);

        return view('ai.intelligence.index', $data);
    }

    /**
     * Mark an anomaly finding as reviewed. The finding must belong to one of
     * the acting user's organizations — the browser never supplies a tenant.
     */
    public function review(Request $request, AiAnomalyFinding $finding): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        if (! in_array($finding->organization_id, $context->organizationIds, true)) {
            abort(403, 'Unauthorized organization scope.');
        }

        if ($finding->status->value !== 'reviewed') {
            $finding->update([
                'status' => 'reviewed',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
        }

        $this->audit->log('ai.financial_anomaly.reviewed', $finding, [], [
            'finding_type' => $finding->type->value,
            'severity' => $finding->severity->value,
            'organization_id' => $finding->organization_id,
        ]);

        return back()->with('success', 'Anomaly finding marked as reviewed.');
    }

    /**
     * Force a predictive-intelligence refresh across every domain for the
     * acting user's organizations/branches. Tenant scope always comes from
     * the trusted context (never from the request), the route is gated by
     * ai.predictive.view, and the action is throttled and audited. The
     * service keeps the operation idempotent per data snapshot.
     */
    public function refreshPredictions(Request $request): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $snapshots = 0;
        $current = 0;

        foreach (PredictiveInsightType::cases() as $type) {
            foreach ($this->predictive->refresh($type, $context->organizationIds, $context->branchIds, $user, true) as $prediction) {
                $snapshots++;
                $current += (int) $prediction->status->isCurrent();
            }
        }

        $this->audit->log('ai.predictive.refreshed', null, [], [
            'organization_count' => count($context->organizationIds),
            'branch_count' => count($context->branchIds),
            'snapshots' => $snapshots,
            'current_snapshots' => $current,
        ]);

        return back()->with(
            'success',
            'Predictive intelligence refreshed for '.count($context->organizationIds).' organization(s); '.$current.' current snapshot(s) across '.$snapshots.' generated/refreshed.'
        );
    }
}
