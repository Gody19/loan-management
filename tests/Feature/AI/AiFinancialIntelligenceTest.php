<?php

namespace Tests\Feature\AI;

use App\AI\Services\AiToolRegistry;
use App\Enums\AiAnomalyFindingStatus;
use App\Enums\FinancialAnomalyType;
use App\Enums\FinancialFindingSeverity;
use App\Models\AiAnomalyFinding;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;

/**
 * Phase 11.7 Financial Intelligence: the six read-only ai.*.view capabilities,
 * their role grant matrix, the deterministic services behind the registered
 * tools, the org-level chat orchestration, the authorized dashboard page and
 * the anomaly review trail.
 *
 * Every assertion is about read-only behavior: tenant scope comes only from
 * the trusted context, browsers never supply a tenant, and the sole write is
 * a human review marker on an anomaly finding.
 */
class AiFinancialIntelligenceTest extends AiTestCase
{
    private const INTELLIGENCE_PERMISSIONS = [
        'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view',
        'ai.trend.view', 'ai.accounting.view', 'ai.anomaly.view',
    ];

    /**
     * The four loan-machine roles plus the finance/review roles, mirroring
     * the seeder grant matrix.
     */
    private const GRANT_MATRIX = [
        'Organization Administrator' => [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view',
            'ai.trend.view', 'ai.accounting.view', 'ai.anomaly.view',
        ],
        'Branch Manager' => [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view',
            'ai.trend.view', 'ai.accounting.view', 'ai.anomaly.view',
        ],
        'Loan Officer' => [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view', 'ai.trend.view',
        ],
        'Credit Officer' => [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view', 'ai.trend.view',
        ],
        'Collection Officer' => [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view',
        ],
        'Treasurer' => [
            'ai.collection.view', 'ai.trend.view', 'ai.accounting.view',
        ],
        'Accountant' => [
            'ai.trend.view', 'ai.accounting.view',
        ],
        'Auditor' => [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view',
            'ai.trend.view', 'ai.accounting.view', 'ai.anomaly.view',
        ],
        'Secretary' => [],
        'VICOBA Member' => [],
    ];

    private function activeLoan(Member $member, array $overrides = []): Loan
    {
        return Loan::factory()->active()->create(array_merge([
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'loan_plan_id' => LoanPlan::factory()->create(['organization_id' => $member->organization_id])->id,
            'principal_amount' => 900000,
            'disbursed_amount' => 900000,
            'total_amount' => 1080000,
            'outstanding_balance' => 800000,
            'amount_paid' => 280000,
            'total_installments' => 12,
        ], $overrides));
    }

    private function toolPayload(string $capability, array $arguments = [], string $question = 'Please report.'): array
    {
        return [
            'capability' => $capability,
            'arguments' => $arguments,
            'question' => $question,
        ];
    }

    private function newFinding(Organization $org, ?Loan $loan = null): AiAnomalyFinding
    {
        return AiAnomalyFinding::create([
            'organization_id' => $org->id,
            'branch_id' => $loan?->branch_id ?? $this->branch($org)->id,
            'member_id' => $loan?->member_id ?? $this->member($org)->id,
            'loan_id' => $loan?->id,
            'type' => FinancialAnomalyType::UnusualLargeOverpayment->value,
            'severity' => FinancialFindingSeverity::Medium->value,
            'title' => 'Unusually large loan repayment',
            'description' => 'Test finding.',
            'amount' => 15000,
            'currency' => 'TZS',
            'source_type' => 'loan',
            'source_id' => $loan?->id ?? 0,
            'metadata' => ['type_label' => 'Unusual Large Overpayment'],
            'detection_date' => now()->toDateString(),
            'detected_at' => now(),
            'status' => AiAnomalyFindingStatus::Detected->value,
        ]);
    }

    // ------------------------------------------------------------------
    // Registry & permissions
    // ------------------------------------------------------------------

    public function test_intelligence_capabilities_are_registered_and_read_only(): void
    {
        $registry = app(AiToolRegistry::class);

        foreach (self::INTELLIGENCE_PERMISSIONS as $capability) {
            $this->assertTrue($registry->has($capability), "{$capability} is not registered.");

            $definition = $registry->definition($capability);

            $this->assertSame([$capability], $definition['permissions']);
            $this->assertSame([], $definition['arguments'], "{$capability} must accept no arguments.");
            $this->assertSame($registry->scope($capability), 'user_org');
        }
    }

    public function test_role_grant_matrix_is_applied_by_the_seeder(): void
    {
        foreach (self::GRANT_MATRIX as $role => $granted) {
            $user = $this->user($role);

            foreach (self::INTELLIGENCE_PERMISSIONS as $permission) {
                $this->assertSame(
                    in_array($permission, $granted, true),
                    $user->can($permission),
                    "Role {$role} has mismatched grant for {$permission}.",
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Tool behavior (POST /ai/tool)
    // ------------------------------------------------------------------

    public function test_portfolio_tool_returns_authoritative_result_for_holder(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $this->activeLoan($this->member($org));

        $this->actingAs($staff);

        $response = $this->postJson('/ai/tool', $this->toolPayload('ai.portfolio.view'));

        $response->assertOk()
            ->assertJsonPath('data.capability', 'ai.portfolio.view');

        $result = $response->json('data.result');

        $this->assertSame(1, $result['active_loans_count']);
        $this->assertSame(1, $result['organization_count']);
        $this->assertEquals(800000.0, $result['total_outstanding']);

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.portfolio.view', $completed->new_values['capability'] ?? null);
    }

    public function test_intelligence_tool_is_denied_without_capability(): void
    {
        $org = $this->makeOrganization();
        // Accountant holds ai.trend.view + ai.accounting.view only.
        $this->actingAs($this->staff($org, 'Accountant'));

        $this->postJson('/ai/tool', $this->toolPayload('ai.portfolio.view'))
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.authorization.denied']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
    }

    public function test_vicoba_member_is_denied_all_intelligence_tools(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        $this->actingAs($this->vicobaUser($org, $member));

        foreach (self::INTELLIGENCE_PERMISSIONS as $capability) {
            $this->postJson('/ai/tool', $this->toolPayload($capability))
                ->assertStatus(403);
        }

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
    }

    // ------------------------------------------------------------------
    // Org-level chat orchestration
    // ------------------------------------------------------------------

    public function test_org_level_question_is_orchestrated_to_delinquency_tool(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $this->actingAs($staff);

        $this->postJson('/ai/chat', ['message' => 'What is our portfolio at risk today?'])->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.delinquency.view', $completed->new_values['capability'] ?? null);
    }

    public function test_org_level_question_is_orchestrated_to_collection_tool(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Treasurer'));

        $this->postJson('/ai/chat', ['message' => 'What is our collection rate this month?'])->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.collection.view', $completed->new_values['capability'] ?? null);
    }

    public function test_org_level_question_denied_for_member_without_capability(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        $this->actingAs($this->vicobaUser($org, $member));

        $this->postJson('/ai/chat', ['message' => 'How is our loan portfolio performing?'])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.completed']);
    }

    // ------------------------------------------------------------------
    // Intelligence dashboard page
    // ------------------------------------------------------------------

    public function test_intelligence_page_renders_for_holder(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $this->actingAs($staff)
            ->get(route('ai.intelligence.index'))
            ->assertOk()
            ->assertSee('Financial Intelligence')
            ->assertSee('Portfolio at risk');

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.financial_intelligence.viewed']);
    }

    public function test_intelligence_page_forbidden_without_capability(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Secretary'));

        $this->get(route('ai.intelligence.index'))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.financial_intelligence.viewed']);
    }

    // ------------------------------------------------------------------
    // Anomaly review trail
    // ------------------------------------------------------------------

    public function test_anomaly_review_marks_finding_reviewed(): void
    {
        $org = $this->makeOrganization();
        $auditor = $this->staff($org, 'Auditor');
        $finding = $this->newFinding($org);

        $this->actingAs($auditor)
            ->post(route('ai.intelligence.anomalies.review', $finding->id))
            ->assertRedirect();

        $finding->refresh();

        $this->assertSame('reviewed', $finding->status->value);
        $this->assertSame($auditor->id, $finding->reviewed_by);
        $this->assertNotNull($finding->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.financial_anomaly.reviewed',
        ]);
    }

    public function test_anomaly_review_forbidden_for_foreign_tenant(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $auditor = $this->staff($orgA, 'Auditor');
        $finding = $this->newFinding($orgB);

        $this->actingAs($auditor)
            ->post(route('ai.intelligence.anomalies.review', $finding->id))
            ->assertForbidden();

        $this->assertSame('detected', $finding->fresh()->status->value);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.financial_anomaly.reviewed']);
    }

    public function test_anomaly_review_requires_anomaly_capability(): void
    {
        $org = $this->makeOrganization();
        $officer = $this->staff($org, 'Loan Officer');
        $finding = $this->newFinding($org);

        $this->actingAs($officer)
            ->post(route('ai.intelligence.anomalies.review', $finding->id))
            ->assertForbidden();

        $this->assertSame('detected', $finding->fresh()->status->value);
    }
}
